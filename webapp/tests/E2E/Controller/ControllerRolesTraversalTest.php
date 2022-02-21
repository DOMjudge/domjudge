<?php declare(strict_types=1);

namespace App\Tests\E2E\Controller;

use App\Tests\Unit\BaseTest;
use Generator;

class ControllerRolesTraversalTest extends BaseTest
{
    protected static string $loginURL = "http://localhost/login";

    /**
     * @See: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch04s25.html
     * Get all combinations of roles with at minimal the starting roles.
     */
    protected function roleCombinations(array $start_roles, array $possible_roles): array
    {
        // Initialize by adding the empty set.
        $results = [$start_roles];

        foreach ($possible_roles as $element) {
            foreach ($results as $combination) {
                $results[] = array_merge([$element], $combination);
            }
        }
        return $results;
    }

    /**
     * Some URLs are not setup in the testing framework or have a function for the
     * user UX/login process, those are skipped.
     **/
    protected function urlExcluded(string $url): bool
    {
        return ($url === '' ||                                //       Empty URL
            $url[0] === '#' ||                                //       Links to local page
            strpos($url, 'http') !== false ||                 //       External links
            strpos($url, '/doc') === 0 ||                     //       Documentation is not setup
            strpos($url, '/api') === 0 ||                     //       API is not functional in framework
            $url === '/logout' ||                             //       Application links
            $url === '/login' ||
            strpos($url, 'activate') !== false ||
            strpos($url, 'deactivate') !== false ||
            strpos($url, '/jury/change-contest/') !== false ||
            strpos($url, '/text') !== false ||                //       Pdfs/text/binaries dirty the report
            strpos($url, '/input') !== false ||               // TODO: Mimetype from the headers
            strpos($url, '/output') !== false ||
            strpos($url, '/export') !== false ||
            strpos($url, '/download') !== false ||
            strpos($url, '/phpinfo') !== false ||
            strpos($url, 'javascript') !== false ||
            strpos($url, '.zip') !== false
        );
    }

    /**
     * Crawl the webpage assume this is allowed and return all other links on the page.
     * @return string[] Found links on crawled URL
     */
    protected function crawlPageGetLinks(string $url, int $statusCode): array
    {
        if($this->urlExcluded($url)) {
            self::fail('The URL should already have been filtered away.');
        }
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        if(($statusCode === 403 || $statusCode === 401) && $response->isRedirection()) {
            self::assertEquals($response->headers->get('location'), $this::$loginURL);
        } else {
            self::assertEquals($statusCode, $response->getStatusCode(), $message);
        }
        $ret = [];
        $tmp = array_unique($crawler->filter('a')->extract(['href']));
        foreach($tmp as $possUrl) {
            if(!$this->urlExcluded($possUrl)) {
                $ret[] = $possUrl;
            }
        }
        return $ret;
    }

    /**
     * Follow all links on a list of pages while new pages are found.
     */
    protected function getAllPages(array $urlsToCheck): array
    {
        $done = [];
        do {
            $toCheck = array_diff($urlsToCheck, $done);
            foreach ($toCheck as $url) {
                if (strpos($url, '/jury/contests') !== false) {
                    continue;
                }
                if (!$this->urlExcluded($url)) {
                    $urlsToCheck = array_unique(array_merge($urlsToCheck, $this->crawlPageGetLinks($url, 200)));
                }
                $done[] = $url;
            }
        }
        while (array_diff($done, $urlsToCheck));
        return $urlsToCheck;
    }

    /**
     * Finds all the pages reachable with $roles on URL $roleBaseURL with optionally traversing all links.
     * @var string[] $roleBaseURL The URL of the current roles.
     * @var string[] $roles The tested roles.
     */
    protected function getPagesRoles(array $roleBaseURL, array $roles, bool $allPages): array
    {
        $this->roles = $roles;
        $this->logOut();
        $this->logIn();
        $urlsFoundPerRole = [];
        foreach ($roleBaseURL as $baseURL) {
            $urlsFoundPerRole[] = $this->crawlPageGetLinks($baseURL, 200);
        }
        $urlsToCheck = array_merge([], ...$urlsFoundPerRole);

        // Find all pages, currently this sometimes breaks as some routes have the same logic.
        if ($allPages) {
            $urlsToCheck = $this->getAllPages($urlsToCheck);
        }
        return $urlsToCheck;
    }

    /**
     * (Sub)Test that having the role(s) gives access to all visible pages.
     * This test should detect mistakes where a page is disallowed when the user has a
     * specific role instead of allowing when the correct role is there.
     * @var string[] $roleURLs
     */
    protected function verifyAccess(array $combinations, array $roleURLs): void
    {
        foreach ($combinations as $static_roles) {
            $this->roles = $static_roles;
            $this->logOut();
            $this->logIn();
            foreach ($roleURLs as $url) {
                if(!$this->urlExcluded($url)) {
                    $this->crawlPageGetLinks($url, 200);
                }
            }
        }
    }

    /**
     * Test that having the team role for example is enough to view pages of that role.
     * This test should detect mistakes where a page is disabled when the user has a
     * certain role instead of allowing when the correct role is there.
     * @var string   $roleBaseURL The base URL of the role.
     * @var string[] $baseRoles The default role of the user.
     * @var string[] $optionalRoles The roles which should not restrict the viewable pages.
     * @dataProvider provideRoleAccessData
     */
    public function testRoleAccess(string $roleBaseURL, array $baseRoles, array $optionalRoles, bool $allPages): void
    {
        $this->roles = $baseRoles;
        $this->logOut();
        $this->logIn();
        $urlsToCheck = $this->crawlPageGetLinks($roleBaseURL, 200);
        if ($allPages) {
            $urlsToCheck = $this->getAllPages($urlsToCheck);
        }
        $combinations = $this->roleCombinations($baseRoles, $optionalRoles);
        $this->verifyAccess($combinations, $urlsToCheck);
    }

    public function visitWithNoContest(string $url, bool $dropdown): void {
        // We only care for the outcome, shorten the code by skipping steps.
        $this->client->followRedirects(true);
        // Explicit set no active contest.
        $this->client->request('GET', '/jury/change-contest/-1');
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        self::assertNotEquals(500, $response->getStatusCode(), sprintf('Failed at %s', $url));
        if ($dropdown) {
            self::assertSelectorExists('a#navbarDropdownContests:contains("no contest")');
        }
    }

    /**
     * Test that having for example the jury role does not allow access to the pages of other roles.
     * @var string   $roleBaseURL The base URL of the role.
     * @var string[] $roleOthersBaseURL The base URLs of the other roles.
     * @var string[] $roles The tested roles.
     * @var string[] $rolesOther The other roles.
     * @dataProvider provideRoleAccessOtherRoles
     */
    public function testRoleAccessOtherRoles(
        string $roleBaseURL,
        array $roleOthersBaseURL,
        array $roles,
        array $rolesOther,
        bool $allPages
    ): void {
        $urlsToCheck        = $this->getPagesRoles([$roleBaseURL], $roles, $allPages);
        $urlsToCheckOther   = $this->getPagesRoles($roleOthersBaseURL, $rolesOther, $allPages);
        $this->roles = $roles;
        $this->logOut();
        $this->logIn();
        foreach (array_diff($urlsToCheckOther, $urlsToCheck) as $url) {
            if (!$this->urlExcluded($url)) {
                $this->crawlPageGetLinks($url, 403);
            }
        }
    }

    /**
     * Test that pages depending on an active contest do not crash on the server.
     * @dataProvider provideNoContestScenario
     */
    public function testNoContestAccess(string $roleBaseURL, array $baseRoles): void
    {
        $this->roles = $baseRoles;
        $this->logOut();
        $this->logIn();
        $urlsToCheck = $this->crawlPageGetLinks($roleBaseURL, 200);
        $urlsToCheck = $this->getAllPages($urlsToCheck);
        foreach($urlsToCheck as $url) {
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
        yield ['/jury',   ['admin'],            ['jury','team','balloon','clarification_rw'],         false];
        yield ['/jury',   ['jury'],             ['admin','team','balloon','clarification_rw'],        false];
        yield ['/jury',   ['balloon'],          ['admin','team','clarification_rw'],                  true];
        yield ['/jury',   ['clarification_rw'], ['admin','team','balloon'],                           true];
        yield ['/team',   ['team'],             ['admin','jury','balloon','clarification_rw'],        true];
        yield ['/public', [],                   ['team','admin','jury','balloon','clarification_rw'], true];
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
        yield ['/jury',   ['/jury','/team'], ['admin'],            ['jury','team'],                                      false];
        yield ['/jury',   ['/jury','/team'], ['jury'],             ['admin','team'],                                     false];
        yield ['/jury',   ['/jury','/team'], ['balloon'],          ['admin','team','clarification_rw'],                  false];
        yield ['/jury',   ['/jury','/team'], ['clarification_rw'], ['admin','team','balloon'],                           false];
        yield ['/team',   ['/jury'],         ['team'],             ['admin','jury','balloon','clarification_rw'],        true];
        yield ['/public', ['/jury','/team'], [],                   ['admin','jury','team','balloon','clarification_rw'], true];
    }

    public function provideNoContestScenario(): Generator
    {
        yield ['/jury', ['admin']];
        yield ['/jury', ['jury']];
        yield ['/jury', ['balloon']];
        yield ['/jury', ['clarification_rw']];
        yield ['/team', ['team']];
    }
}
