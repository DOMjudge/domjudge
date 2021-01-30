<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\BaseTest;
use Generator;

class ControllerRolesTest extends BaseTest
{
    protected static $loginURL = "http://localhost/login";

    /**
     * @See: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch04s25.html
     * Get all combinations of roles with at minimal the starting roles
     */
    protected function roleCombinations(array $start_roles, array $possible_roles) : array
    {
        // initialize by adding the empty set
        $results = array($start_roles);

        foreach ($possible_roles as $element) {
            foreach ($results as $combination) {
                $results[] = array_merge(array($element), $combination);
            }
        }
        return $results;
    }

    /**
     * Some URLs are not setup in the testing framework or have a function for the
     * user UX/login process, those are skipped.
     **/
    protected function urlExcluded(string $url) : bool
    {
        return ($url === '' ||                                                 // Empty URL
            $url[0] === '#' ||                                          // Links to local page
            strpos($url, 'http') !== false ||                        // External links
            strpos($url, '/doc') === 0 ||                     // Documentation is not setup
            strpos($url, '/api') === 0 ||                     // API is not functional in framework
            strpos($url, '/delete') !== false ||                     // Breaks MockData
            strpos($url, '/add') !== false ||                        //TODO: Should be fixable
            strpos($url, '/edit') !== false ||
            $url === '/logout' ||                                           // Application links
            $url === '/login' ||
            strpos($url, 'activate') !== false ||
            strpos($url, 'deactivate') !== false ||
            strpos($url, '/jury/change-contest/') !== false ||
            strpos($url, '/text') !== false ||                       // Pdfs/text/binaries dirty the report
            strpos($url, '/input') !== false ||                      // TODO: mimetype from the headers
            strpos($url, '/output') !== false ||
            strpos($url, '/export') !== false ||
            strpos($url, '/download') !== false ||
            strpos($url, '.zip') !== false
        );
    }

    /**
     * Crawl the webpage assume this is allowed and return all other links on the page
     * @return string[] Found links on crawled URL
     */
    protected function crawlPageGetLinks(string $url, int $statusCode) : array
    {
        if($this->urlExcluded($url)) {
            return [];
        }
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        if($statusCode == '403' && $response->isRedirection()) {
            self::assertEquals($response->headers->get('location'), $this::$loginURL);
        } else {
            self::assertEquals($statusCode, $response->getStatusCode(), $message);
        }
        return array_unique($crawler->filter('a')->extract(['href']));
    }

    /**
     * Follow all links on a list of pages while new pages are found
     */
    protected function getAllPages(array $urlsToCheck) : array
    {
        $done = array();
        do {
            $toCheck = array_diff($urlsToCheck,$done);
            foreach ($toCheck as $url) {
                if (strpos($url,'/jury/contests') !== false) {
                    continue;
                } else {
                    if (!$this->urlExcluded($url)) {
                        $urlsToCheck = array_unique(array_merge($urlsToCheck, $this->crawlPageGetLinks($url, 200)));
                    }
                    $done[] = $url;
                }
            }
        }
        while (array_diff($done,$urlsToCheck));
        return $urlsToCheck;
    }

    /**
     * Finds all the pages reachable with $roles on URL $roleBaseURL with optionally traversing all links
     * @var string[] $roleBaseURL The URL of the current Roles
     * @var string[] $roles The tested Roles,
     */
    protected function getPagesRoles(array $roleBaseURL, array $roles, bool $allPages) : array
    {
        static::$roles = $roles;
        $this->logIn();
        $urlsToCheck = [];
        foreach ($roleBaseURL as $baseURL) {
            $urlsToCheck = array_merge($urlsToCheck, $this->crawlPageGetLinks($baseURL, 200));
        }

        // Find all pages, currently this sometimes breaks as some routes have the same logic
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
    protected function verifyAccess(array $combinations, array $roleURLs) : void
    {
        foreach ($combinations as $static_roles) {
            static::$roles = $static_roles;
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
     * @var string[] $baseRoles The standard role of the user
     * @var string[] $optionalRoles The roles which should not restrict the viewable pages
     * @dataProvider provideRoleAccessData
     */
    public function testRoleAccess(string $roleBaseURL, array $baseRoles, array $optionalRoles, bool $allPages) : void
    {
        static::$roles = $baseRoles;
        $this->logIn();
        $urlsToCheck = $this->crawlPageGetLinks($roleBaseURL, 200);
        if ($allPages) {
            $urlsToCheck = $this->getAllPages($urlsToCheck);
        }
        $combinations = $this->roleCombinations($baseRoles, $optionalRoles);
        $this->verifyAccess($combinations, $urlsToCheck);
    }

    /**
     * Test that having for example the jury role does not allow access to the pages of other roles.
     * @var string[] $roleOthersBaseURL The BaseURLs of the other roles
     * @var string[] $roles The tested Roles,
     * @var string[] $rolesOther The other Roles
     * @dataProvider provideRoleAccessOtherRoles
     */
    public function testRoleAccessOtherRoles(
        string $roleBaseURL, array $roleOthersBaseURL,
        array $roles, array $rolesOther,
        bool $allPages
    ) : void
    {
        $urlsToCheck        = $this->getPagesRoles([$roleBaseURL], $roles, $allPages);
        $urlsToCheckOther   = $this->getPagesRoles($roleOthersBaseURL, $rolesOther, $allPages);
        static::$roles = $roles;
        $this->logIn();
        foreach (array_diff($urlsToCheckOther, $urlsToCheck) as $url) {
            if (!$this->urlExcluded($url)) {
                $this->crawlPageGetLinks($url, 403);
            }
        }
    }

    /**
     * Data provider used to test role access. Each item contains:
     * - the page to visit
     * - the base roles the user has
     * - additional roles to add to the user
     * - Whether to also recursively visit linked pages
     */
    public function provideRoleAccessData() : Generator
    {
        yield ['/jury',     ['admin'],  ['jury','team'],            false];
        yield ['/jury',     ['jury'],   ['admin','team'],           false];
        yield ['/team',     ['team'],   ['admin','jury'],           true];
        yield ['/public',   [],         ['team','admin','jury'],    true];
    }

    /**
     * Data provider used to test if having one role does not add access of other roles
     * Each item contains:
     * - the base page of the tested role
     * - the base pages of the other roles
     * - the tested role
     * - the other excluded roles
     * - Whether to also recursively visit linked pages
     **/
    public function provideRoleAccessOtherRoles() : Generator
    {
        yield ['/jury',     ['/jury','/team'],  ['admin'],  ['jury','team'],            false];
        yield ['/jury',     ['/jury','/team'],  ['jury'],   ['admin','team'],           false];
        yield ['/team',     ['/jury'],          ['team'],   ['admin','jury'],           true];
        yield ['/public',   ['/jury','/team'],  [],         ['admin','jury','team'],    true];
    }
}
