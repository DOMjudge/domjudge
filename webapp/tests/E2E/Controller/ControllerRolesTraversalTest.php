<?php declare(strict_types=1);

namespace App\Tests\E2E\Controller;

use App\Tests\Unit\BaseTestCase;
use Generator;

class ControllerRolesTraversalTest extends BaseTestCase
{
    protected static string $loginURL = "http://localhost/login";

    /**
     * 'http';                  //       External links
     * 'activate';
     * 'deactivate';
     * '/jury/change-contest/';
     * '/text';                 //       PDFs/text/binaries dirty the report
     * '/input';                // TODO: Mimetype from the headers
     * '/output';
     * '/export';
     * '/download';
     * 'javascript';
     * '.zip';
     * ''                       //       Empty URL
     * '#'                      //       Links to local page
     * '/logout'                //       Application links
     * '/login'
     **/
    protected static array $substrings = ['http','activate','deactivate','/jury/change-contest/','/statement','/input','/output','/export','/download','javascript','.zip'];
    protected static array $fullstrings = ['','#','/logout','/login'];
    protected static array $riskyURLs = ['nonExistent','2nd'];

    protected function getLoops(): array
    {
        $shadowModes = $this->getShadowModeLoops()['shadowModes'];
        $riskyURLs = [];
        if (array_key_exists('CRAWL_RISKY', getenv())) {
            $riskyURLs = explode(',', getenv('CRAWL_RISKY'));
        } elseif (!array_key_exists('CRAWL_ALL', getenv())) {
            $riskyURLs = array_slice(self::$riskyURLs, 0, 1);
        }
        return ['shadowModes' => $shadowModes, 'riskyURLs' => $riskyURLs];
    }

    /**
     * @see: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch04s25.html
     * Get all combinations of roles with at minimal the starting roles.
     */
    protected function roleCombinations(array $start_roles, array $possible_roles): array
    {
        // Initialize by adding the empty set.
        $results = [$start_roles];

        foreach ($possible_roles as $element) {
            foreach ($results as $combination) {
                $results[] = [$element, ...$combination];
            }
        }
        return $results;
    }

    /**
     * Some URLs are not setup in the testing framework or have a function for the
     * user UX/login process, those are skipped.
     **/
    protected function urlExcluded(string $url, string $skip): bool
    {
        foreach (self::$substrings as $subs) {
            if (str_contains($url, $subs) && $subs !== $skip) {
                return true;
            }
        }
        foreach (self::$fullstrings as $fuls) {
            if ($url === $fuls && $fuls !== $skip) {
                return true;
            }
        }
        // Documentation is not setup
        // API is not functional in framework
        foreach (['/doc','/api'] as $extension) {
            if (str_starts_with($url, $extension)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Crawl the webpage assume this is allowed and return all other links on the page.
     * @return string[] Found links on crawled URL
     */
    protected function crawlPageGetLinks(string $url, int $statusCode, string $skip): array
    {
        if ($this->urlExcluded($url, $skip)) {
            self::fail('The URL should already have been filtered away.');
        }
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        if (($statusCode === 403 || $statusCode === 401) && $response->isRedirection()) {
            self::assertEquals($response->headers->get('location'), $this::$loginURL);
        } elseif (($response->getStatusCode() === 302 ) && $response->isRedirection()) {
            if (str_contains($url, '/jury/external-contest')) {
                self::assertTrue(str_contains($response->headers->get('location'), '/jury/external-contest/manage'));
            } elseif (str_contains($url, '/jury/shadow-differences')) {
                self::assertTrue(str_contains($response->headers->get('location'), '/jury'));
            } elseif (str_contains($url, '/jury/submissions') || str_contains($url, '/jury/clarifications') || str_contains($url, '/jury/balloons')) {
                // Legacy routes redirect to contest-scoped routes
                self::assertTrue(str_contains($response->headers->get('location'), '/jury/contests/'));
            } else {
                self::assertTrue(str_contains($response->headers->get('location'), '/public'));
            }
        } else {
            // The public URL can always be accessed but is not linked for every role.
            if (str_contains($url, '/public')) {
                $statusCode = 200;
            }
            self::assertEquals($statusCode, $response->getStatusCode(), 'Unexpected response code for ' . $url);
        }
        $ret = [];
        $tmp = array_unique($crawler->filter('a')->extract(['href']));
        foreach ($tmp as $possUrl) {
            if (!$this->urlExcluded($possUrl, $skip)) {
                $ret[] = $possUrl;
                if (!str_contains($possUrl, '#')) {
                    $ret[] = $possUrl.'#';
                }
            }
        }
        return $ret;
    }

    /**
     * Follow all links on a list of pages while new pages are found.
     */
    protected function getAllPages(array $urlsToCheck, string $skip): array
    {
        $done = [];
        do {
            $toCheck = array_diff($urlsToCheck, $done);
            foreach ($toCheck as $url) {
                if (str_contains($url, '/jury/contests')) {
                    continue;
                }
                if (!$this->urlExcluded($url, $skip)) {
                    $urlsToCheck = array_unique([...$urlsToCheck, ...$this->crawlPageGetLinks($url, 200, $skip)]);
                }
                $done[] = $url;
            }
        } while (array_diff($done, $urlsToCheck));
        return $urlsToCheck;
    }

    /**
     * Finds all the pages reachable with $roles on URL $roleBaseURL with optionally traversing all links.
     * @param string[] $roleBaseURL The URL of the current roles.
     * @param string[] $roles The tested roles.
     */
    protected function getPagesRoles(array $roleBaseURL, array $roles, bool $allPages, string $skip): array
    {
        $this->roles = $roles;
        $this->logOut();
        $this->logIn();
        $urlsFoundPerRole = [];
        foreach ($roleBaseURL as $baseURL) {
            $urlsFoundPerRole[] = $this->crawlPageGetLinks($baseURL, 200, $skip);
        }
        $urlsToCheck = array_merge([], ...$urlsFoundPerRole);

        // Find all pages, currently this sometimes breaks as some routes have the same logic.
        if ($allPages) {
            $urlsToCheck = $this->getAllPages($urlsToCheck, $skip);
        }
        return $urlsToCheck;
    }

    /**
     * (Sub)Test that having the role(s) gives access to all visible pages.
     * This test should detect mistakes where a page is disallowed when the user has a
     * specific role instead of allowing when the correct role is there.
     * @param string[] $roleURLs
     */
    protected function verifyAccess(array $combinations, array $roleURLs, string $skip): void
    {
        foreach ($combinations as $static_roles) {
            $this->roles = $static_roles;
            $this->logOut();
            $this->logIn();
            foreach ($roleURLs as $url) {
                if (!$this->urlExcluded($url, $skip)) {
                    $this->crawlPageGetLinks($url, 200, $skip);
                }
            }
        }
    }

    /**
     * Test that having the team role for example is enough to view pages of that role.
     * This test should detect mistakes where a page is disabled when the user has a
     * certain role instead of allowing when the correct role is there.
     * @param string   $roleBaseURL The base URL of the role.
     * @param string[] $baseRoles The default role of the user.
     * @param string[] $optionalRoles The roles which should not restrict the viewable pages.
     * @param bool     $shadowMode Put the installation in this shadow mode.
     * @dataProvider provideRoleAccessData
     */
    public function testRoleAccess(string $roleBaseURL, array $baseRoles, array $optionalRoles, bool $allPages, bool $shadowMode, string $skip): void
    {
        $this->setupShadowMode($shadowMode);
        $this->roles = $baseRoles;
        $this->logOut();
        $this->logIn();
        $urlsToCheck = $this->crawlPageGetLinks($roleBaseURL, 200, $skip);
        if ($allPages) {
            $urlsToCheck = $this->getAllPages($urlsToCheck, $skip);
        }
        $combinations = $this->roleCombinations($baseRoles, $optionalRoles);
        $this->verifyAccess($combinations, $urlsToCheck, $skip);
    }

    public function visitWithNoContest(string $url, bool $dropdown): void
    {
        // We only care for the outcome, shorten the code by skipping steps.
        $this->client->followRedirects(true);
        // Explicit set no active contest.
        $this->client->request('GET', '/jury/change-contest/-1');
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        self::assertNotEquals(500, $response->getStatusCode(), sprintf('Failed at %s', $url));
        // Contest-scoped URLs (e.g., /jury/contests/demo/...) have the contest set from the URL,
        // so we don't expect "no contest" in the dropdown for those.
        if ($dropdown && !str_contains($url, '/public') && !str_contains($url, '/jury/contests/')) {
            try {
                self::assertSelectorExists('a#navbarDropdownContests:contains("no contest")', "Failed at: " . $url);
            } catch (\Exception $e) {
                if (getenv('CI')) { // We're running in GHA.
                    $ciArtifacts = getenv('ARTIFACTS');
                    if ($ciArtifacts != '') {
                        $fileHandler = fopen(sprintf("%s/%s", $ciArtifacts, str_replace('/', '_s_', $url)), 'w');
                        fwrite($fileHandler, $response->getContent());
                    }
                }
                throw $e;
            }
        }
    }

    /**
     * Test that having for example the jury role does not allow access to the pages of other roles.
     * @param string   $roleBaseURL The base URL of the role.
     * @param string[] $roleOthersBaseURL The base URLs of the other roles.
     * @param string[] $roles The tested roles.
     * @param string[] $rolesOther The other roles.
     * @dataProvider provideRoleAccessOtherRoles
     */
    public function testRoleAccessOtherRoles(
        string $roleBaseURL,
        array $roleOthersBaseURL,
        array $roles,
        array $rolesOther,
        bool $allPages,
        bool $shadowMode,
        string $skip
    ): void {
        $this->setupShadowMode($shadowMode);
        $urlsToCheck        = $this->getPagesRoles([$roleBaseURL], $roles, $allPages, $skip);
        $urlsToCheckOther   = $this->getPagesRoles($roleOthersBaseURL, $rolesOther, $allPages, $skip);
        $this->roles = $roles;
        $this->logOut();
        $this->logIn();
        foreach (array_diff($urlsToCheckOther, $urlsToCheck) as $url) {
            if (!$this->urlExcluded($url, $skip)) {
                $this->crawlPageGetLinks($url, 403, $skip);
            }
        }
    }

    /**
     * Test that pages depending on an active contest do not crash on the server.
     * @dataProvider provideNoContestScenario
     */
    public function testNoContestAccess(string $roleBaseURL, array $baseRoles, bool $shadowMode, string $skip): void
    {
        $this->setupShadowMode($shadowMode);
        $this->roles = $baseRoles;
        $this->logOut();
        $this->logIn();
        $urlsToCheck = $this->crawlPageGetLinks($roleBaseURL, 200, $skip);
        $urlsToCheck = $this->getAllPages($urlsToCheck, $skip);
        foreach ($urlsToCheck as $url) {
            $this->visitWithNoContest($url, $roleBaseURL !== '/team');
        }
    }

    /**
     * Data provider used to test role access. Each item contains:
     * - the page to visit,
     * - the base roles the user has,
     * - additional roles to add to the user,
     * - whether to also recursively visit linked pages.
     */
    public function provideRoleAccessData(): Generator
    {
        ['shadowModes' => $shadowModes, 'riskyURLs' => $riskyURLs] = $this->getLoops();
        foreach ($riskyURLs as $skip) {
            foreach ($shadowModes as $str_shadow_mode) {
                $shadow_mode = (bool)$str_shadow_mode;
                yield ['/jury',   ['admin'],            ['jury','team','balloon','clarification_rw'],         false, $shadow_mode, $skip];
                yield ['/jury',   ['jury'],             ['admin','team','balloon','clarification_rw'],        false, $shadow_mode, $skip];
                yield ['/jury',   ['balloon'],          ['admin','team','clarification_rw'],                  true,  $shadow_mode, $skip];
                yield ['/jury',   ['clarification_rw'], ['admin','team','balloon'],                           true,  $shadow_mode, $skip];
                yield ['/team',   ['team'],             ['admin','jury','balloon','clarification_rw'],        true,  $shadow_mode, $skip];
                yield ['/public', [],                   ['team','admin','jury','balloon','clarification_rw'], true,  $shadow_mode, $skip];
            }
        }
    }

    /**
     * Data provider used to test if having one role does not add access of other roles
     * Each item contains:
     * - the base page of the tested role,
     * - the base pages of the other roles,
     * - the tested role,
     * - the other excluded roles,
     * - whether to also recursively visit linked pages.
     **/
    public function provideRoleAccessOtherRoles(): Generator
    {
        ['shadowModes' => $shadowModes, 'riskyURLs' => $riskyURLs] = $this->getLoops();
        foreach ($riskyURLs as $skip) {
            foreach ($shadowModes as $str_shadow_mode) {
                $shadow_mode = (bool)$str_shadow_mode;
                yield ['/jury',   ['/jury','/team'], ['admin'],            ['jury','team'],                                        false, $shadow_mode, $skip];
                yield ['/jury',   ['/jury','/team'], ['jury'],             ['admin','team'],                                       false, $shadow_mode, $skip];
                yield ['/jury',   ['/jury','/team'], ['balloon'],          ['admin','team','clarification_rw'],                    false, $shadow_mode, $skip];
                yield ['/jury',   ['/jury','/team'], ['clarification_rw'], ['admin','team','balloon'],                             false, $shadow_mode, $skip];
                yield ['/team',   ['/jury'],         ['team'],             ['admin','jury','balloon','clarification_rw'],          true, $shadow_mode, $skip];
                yield ['/public', ['/jury','/team'], [],                   ['admin','jury','team','balloon','clarification_rw'],   true, $shadow_mode, $skip];
            }
        }
    }

    public function provideNoContestScenario(): Generator
    {
        ['shadowModes' => $shadowModes, 'riskyURLs' => $riskyURLs] = $this->getLoops();
        foreach ($riskyURLs as $skip) {
            foreach ($shadowModes as $str_shadow_modes) {
                $shadow_mode = (bool)$str_shadow_modes;
                yield ['/jury', ['admin'],            $shadow_mode, $skip];
                yield ['/jury', ['jury'],             $shadow_mode, $skip];
                yield ['/jury', ['balloon'],          $shadow_mode, $skip];
                yield ['/jury', ['clarification_rw'], $shadow_mode, $skip];
                yield ['/team', ['team'],             $shadow_mode, $skip];
            }
        }
    }
}
