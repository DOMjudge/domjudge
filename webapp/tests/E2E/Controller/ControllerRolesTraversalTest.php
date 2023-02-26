<?php declare(strict_types=1);

namespace App\Tests\E2E\Controller;

use App\Tests\Unit\BaseTest;
use Generator;

class ControllerRolesTraversalTest extends BaseTest
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
    protected static array $substrings = ['http','activate','deactivate','/jury/change-contest/','/text','/input','/output','/export','/download','javascript','.zip'];
    protected static array $fullstrings = ['','#','/logout','/login'];
    protected static array $riskyURLs = ['nonExistent','2nd'];

    protected function getLoops(): array
    {
        $dataSources = $this->getDatasourceLoops()['dataSources'];
        $riskyURLs = [];
        if (array_key_exists('CRAWL_RISKY', getenv())) {
            $riskyURLs = explode(',', getenv('CRAWL_RISKY'));
        } elseif (!array_key_exists('CRAWL_ALL', getenv())) {
            $riskyURLs = array_slice(self::$riskyURLs, 0, 1);
        }
        return ['dataSources' => $dataSources, 'riskyURLs' => $riskyURLs];
    }

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
    protected function urlExcluded(string $url, string $skip): bool
    {
        foreach (self::$substrings as $subs) {
            if (strpos($url, $subs) !== false && $subs !== $skip) {
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
            if (strpos($url, $extension) === 0) {
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
            if (strpos($url, '/jury/external-contest') !== false) {
                self::assertTrue(strpos($response->headers->get('location'), '/jury/external-contest/manage') !== false);
            } else {
                self::assertTrue(strpos($response->headers->get('location'), '/public') !== false);
            }
        } else {
            // The public URL can always be accessed but is not linked for every role.
            if (strpos($url, '/public') !== false) {
                $statusCode = 200;
            }
            self::assertEquals($statusCode, $response->getStatusCode(), 'Unexpected response code for ' . $url);
        }
        $ret = [];
        $tmp = array_unique($crawler->filter('a')->extract(['href']));
        foreach ($tmp as $possUrl) {
            if (!$this->urlExcluded($possUrl, $skip)) {
                $ret[] = $possUrl;
                if (strpos($possUrl, '#') === false) {
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
                if (strpos($url, '/jury/contests') !== false) {
                    continue;
                }
                if (!$this->urlExcluded($url, $skip)) {
                    $urlsToCheck = array_unique(array_merge($urlsToCheck, $this->crawlPageGetLinks($url, 200, $skip)));
                }
                $done[] = $url;
            }
        } while (array_diff($done, $urlsToCheck));
        return $urlsToCheck;
    }

    /**
     * Finds all the pages reachable with $roles on URL $roleBaseURL with optionally traversing all links.
     * @var string[] $roleBaseURL The URL of the current roles.
     * @var string[] $roles The tested roles.
     */
    protected function getPagesRoles(array $roleBaseURL, array $roles, bool $allPages, $skip): array
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
     * @var string[] $roleURLs
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
     * @var string   $roleBaseURL The base URL of the role.
     * @var string[] $baseRoles The default role of the user.
     * @var string[] $optionalRoles The roles which should not restrict the viewable pages.
     * @var int      $dataSource Put the installation in this dataSource mode.
     * @dataProvider provideRoleAccessData
     */
    public function testRoleAccess(string $roleBaseURL, array $baseRoles, array $optionalRoles, bool $allPages, int $dataSource, string $skip): void
    {
        $this->setupDatasource($dataSource);
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
        if ($dropdown && strpos($url, '/public') === false) {
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
        bool $allPages,
        int $dataSource,
        string $skip
    ): void {
        $this->setupDataSource($dataSource);
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
    public function testNoContestAccess(string $roleBaseURL, array $baseRoles, int $dataSource, $skip): void
    {
        $this->setupDataSource($dataSource);
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
        extract($this->getLoops());
        foreach ($riskyURLs as $skip) {
            foreach ($dataSources as $str_data_source) {
                $data_source = (int)$str_data_source;
                yield ['/jury',   ['admin'],            ['jury','team','balloon','clarification_rw'],         false, $data_source, $skip];
                yield ['/jury',   ['jury'],             ['admin','team','balloon','clarification_rw'],        false, $data_source, $skip];
                yield ['/jury',   ['balloon'],          ['admin','team','clarification_rw'],                  true,  $data_source, $skip];
                yield ['/jury',   ['clarification_rw'], ['admin','team','balloon'],                           true,  $data_source, $skip];
                yield ['/team',   ['team'],             ['admin','jury','balloon','clarification_rw'],        true,  $data_source, $skip];
                yield ['/public', [],                   ['team','admin','jury','balloon','clarification_rw'], true,  $data_source, $skip];
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
        extract($this->getLoops());
        foreach ($riskyURLs as $skip) {
            foreach ($dataSources as $str_data_source) {
                $data_source = (int)$str_data_source;
                yield ['/jury',   ['/jury','/team'], ['admin'],            ['jury','team'],                                        false, $data_source, $skip];
                yield ['/jury',   ['/jury','/team'], ['jury'],             ['admin','team'],                                       false, $data_source, $skip];
                yield ['/jury',   ['/jury','/team'], ['balloon'],          ['admin','team','clarification_rw'],                    false, $data_source, $skip];
                yield ['/jury',   ['/jury','/team'], ['clarification_rw'], ['admin','team','balloon'],                             false, $data_source, $skip];
                yield ['/team',   ['/jury'],         ['team'],             ['admin','jury','balloon','clarification_rw'],          true, $data_source, $skip];
                yield ['/public', ['/jury','/team'], [],                   ['admin','jury','team','balloon','clarification_rw'],   true, $data_source, $skip];
            }
        }
    }

    public function provideNoContestScenario(): Generator
    {
        extract($this->getLoops());
        foreach ($riskyURLs as $skip) {
            foreach ($dataSources as $str_data_source) {
                $data_source = (int)$str_data_source;
                yield ['/jury', ['admin'],            $data_source, $skip];
                yield ['/jury', ['jury'],             $data_source, $skip];
                yield ['/jury', ['balloon'],          $data_source, $skip];
                yield ['/jury', ['clarification_rw'], $data_source, $skip];
                yield ['/team', ['team'],             $data_source, $skip];
            }
        }
    }
}
