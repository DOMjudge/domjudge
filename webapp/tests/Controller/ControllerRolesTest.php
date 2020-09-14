<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\BaseTest;

class ControllerRolesTest extends BaseTest
{
    protected static $roles = [];

    /**
     * //See: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch04s25.html
     * Get all combinations of roles with at minimal the starting roles
     * @return array $results
     * @var string[] $possible_roles
     * @var string[] $start_roles
     */
    protected function roleCombinations(array $start_roles, array $possible_roles)
    {
        // initialize by adding the empty set
        $results = array($start_roles);

        foreach ($possible_roles as $element) {
            foreach ($results as $combination) {
                array_push($results, array_merge(array($element), $combination));
            }
        }
        return $results;
    }

    /**
     * Crawl the webpage assume this is allowed and return all other links on the page
     * @return string[] $urlsToCheck
     * @var string $url
     * @var int $statusCode
     */
    public function crawlPage(string $url, int $statusCode)
    {
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals($statusCode, $response->getStatusCode(), $message);
        return array_unique($crawler->filter('a')->extract(['href']));
    }

    /**
     * Test that having the role(s) gives access to all visible pages.
     * This test should detect mistakes where a page is disabled when the user has a
     * certain role instead of allowing when the correct role is there.
     * @var string[] $combinations
     * @var string[] $roleURLs
     */
    private function verifyAccess(array $combinations, array $roleURLs)
    {
        foreach ($combinations as static::$roles) {
            foreach ($roleURLs as $url) {
                // Documentation is not setup in the UnitTesting framework
                if (substr($url, 0, 4) == '/doc') {
                    continue;
                } // The change-contest handles a different action
                elseif (substr($url, 0, 21) == '/jury/change-contest/') {
                    $this->crawlPage($url, 302);
                } //Remove links to local page, external or application links
                elseif ($url[0] != '#' && $url[0] != 'h' && $url != '/logout') {
                    $this->crawlPage($url, 200);
                }
            }
        }
    }

    /**
     * Test that having the team role for example is enough to view pages of that role.
     * This test should detect mistakes where a page is disabled when the user has a
     * certain role instead of allowing when the correct role is there.
     * @var string $roleBaseURL The standard endpoint from where the user traverses the website
     * @var string[] $baseRoles The standard role of the user
     * @var string[] $optionalRoles The roles which should not restrict the viewable pages
     * @dataProvider provideBasePages
     */
    public function RoleAccess(string $roleBaseURL, array $baseRoles, array $optionalRoles)
    {
        static::$roles = $baseRoles;
        $this->logIn();
        $urlsToCheck = $this->crawlPage($roleBaseURL, 200);
        $combinations = $this->roleCombinations($baseRoles, $optionalRoles);
        $this->verifyAccess($combinations, $urlsToCheck);
    }

    public function provideBasePages()
    {
        return [
            ['/jury', ['admin'],    ['jury','team']],
            ['/jury', ['jury'],     ['admin','team']],
            ['/team', ['team'],     ['admin','jury']]
        ];
    }

    /**
     * Test that having for example the jury role does not allow access to the pages of other roles.
     * @dataProvider provideBaseURLAndRoles
     * @var string $roleBaseURL The URL of the current Role
     * @var string[] $roleOthersBaseURL The BaseURLs of the other roles
     * @var string[] $role The tested Role,
     * @var string[] $rolesOther The other Roles
     */
    public function testRoleAccessOtherRoles(
        string $roleBaseURL,
        array $roleOthersBaseURL,
        array $role,
        array $rolesOther
    ) {
        static::$roles = $rolesOther;
        $this->logIn();
        $urlsToCheck = [];
        foreach ($roleOthersBaseURL as $baseURL) {
            $urlsToCheck = array_merge($urlsToCheck, $this->crawlPage($baseURL, 200));
        }

        // Find all pages
        $done = array();
        do {
            $toCheck = array_diff($done,$urlsToCheck);
            foreach ($toCheck as $url) {
                $urlsToCheck = array_merge($urlsToCheck, $this->crawlPage($url, 200));
                $done[] = $url;
            }
        }
        while (array_diff($done,$urlsToCheck));

        // Now check the rights of our user with the current role
        static::$roles = $role;
        $this->logIn();
        $urlsToCheckRole = $this->crawlPage($roleBaseURL, 200);
        foreach (array_diff($urlsToCheck, $urlsToCheckRole) as $url) {
            print($url."\n");
            // Documentation is not setup in the UnitTesting framework
            if (substr($url, 0, 4) == '/doc') {
                continue;
            }
            // The change-contest handles a different action
            if (substr($url, 0, 21) == '/jury/change-contest/') {
                continue;
            }
            // Remove links to local page, external or application links
            if ($url[0] == '#' || $url[0] == 'h' || $url != '/logout') {
                continue;
            }
            $this->crawlPage($url, 403);
        }
    }

    public function provideBaseURLAndRoles()
    {
        return [
            ['/jury', ['/jury','/team'],    ['admin'],  ['jury','team']],
            ['/jury', ['/jury','/team'],    ['jury'],   ['admin','team']],
            ['/team', ['/jury'],            ['team'],   ['admin','jury']],
        ];
    }
}
