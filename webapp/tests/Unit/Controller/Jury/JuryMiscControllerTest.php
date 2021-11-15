<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;
use Generator;

class JuryMiscControllerTest extends BaseTest
{
    protected $roles = ['jury'];

    /**
     * Test that if no user is logged in the user gets redirected to the login page.
     */
    public function testJuryRedirectToLogin() : void
    {
        $this->logOut();

        $this->verifyPageResponse('GET', '/jury', 302, 'http://localhost/login');
    }

    /**
     * Test the login process for a jury member.
     */
    public function testLogin(): void
    {
        $this->logOut();

        // Make sure the suer has the correct permissions.
        $this->setupUser();

        // Test incorrect and correct password.
        $this->loginHelper('demo', 'foo', 'http://localhost/login', 401);
        $this->loginHelper('demo', 'demo', 'http://localhost/jury', 200);
    }

    /**
     * Test that the jury index page works.
     */
    public function testJuryIndexPage(): void
    {
        $this->client->request('GET', '/jury');

        $this->verifyPageResponse('GET', '/jury', 200);
        self::assertSelectorExists('html:contains("DOMjudge Jury interface")');
    }

    /**
     * Test that the ajax endpoints return the correct data.
     *
     * @dataProvider provideJuryAjax
     */
    public function testJuryAjax(string $endpoint, int $status, array $newRoles, array $finalObject): void {
        $url = '/jury/ajax/'.$endpoint;
        $this->roles = $newRoles;
        $this->logOut();
        $this->logIn();
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        self::assertEquals($status, $response->getStatusCode());
        if ($status !== 403) {
            $object = json_decode($response->getContent(), true);
            self::assertEquals($object, $finalObject);
        }
    }

    public function provideJuryAjax(): Generator
    {
        foreach([200 => ['balloon','jury','admin'], 403 => ['team']] as $status=>$roles) {
            foreach ($roles as $role) {
                yield ['affiliations', $status, [$role], ['results' => [0 => ['id' => 1,
                                                                              'text' => 'Utrecht University (1)']
                                                                       ]]];
                yield ['locations', $status, [$role], ['results' => []]];
            }
        }
        foreach ([200 => ['jury','admin'], 403 => ['balloon','team']] as $status=>$roles) {
            foreach ($roles as $role) {
                yield ['problems', $status, [$role], ['results' => [0 => ['id' => 3, 'text' => 'Boolean switch search (p3)'],
                                                                    1 => ['id' => 2,
                                                                          'text' => 'Float special compare test (p2)'],
                                                                    2 => ['id' => 1, 'text' => 'Hello World (p1)']]]];
                yield ['teams', $status, [$role], ['results' => [0 => ['id' => 1, 'text' => 'DOMjudge (t1)'],
                                                                 1 => ['id' => 2, 'text' => 'Example teamname (t2)']]]];
                yield ['languages', $status, [$role], ['results' => [0 => ['id' => 'adb', 'text' => 'Ada (adb)'],
                                                                     1 => ['id' => 'awk', 'text' => 'AWK (awk)'],
                                                                     2 => ['id' => 'bash', 'text' => 'Bash shell (bash)'],
                                                                     3 => ['id' => 'c', 'text' => 'C (c)'],
                                                                     4 => ['id' => 'csharp', 'text' => 'C# (csharp)'],
                                                                     5 => ['id' => 'cpp', 'text' => 'C++ (cpp)'],
                                                                     6 => ['id' => 'f95', 'text' => 'Fortran (f95)'],
                                                                     7 => ['id' => 'hs', 'text' => 'Haskell (hs)'],
                                                                     8 => ['id' => 'java', 'text' => 'Java (java)'],
                                                                     9 => ['id' => 'js', 'text' => 'JavaScript (js)'],
                                                                     10 => ['id' => 'kt', 'text' => 'Kotlin (kt)'],
                                                                     11 => ['id' => 'lua', 'text' => 'Lua (lua)'],
                                                                     12 => ['id' => 'pas', 'text' => 'Pascal (pas)'],
                                                                     13 => ['id' => 'pl', 'text' => 'Perl (pl)'],
                                                                     14 => ['id' => 'sh', 'text' => 'POSIX shell (sh)'],
                                                                     15 => ['id' => 'plg', 'text' => 'Prolog (plg)'],
                                                                     16 => ['id' => 'py3', 'text' => 'Python 3 (py3)'],
                                                                     17 => ['id' => 'r', 'text' => 'R (r)'],
                                                                     18 => ['id' => 'rb', 'text' => 'Ruby (rb)'],
                                                                     19 => ['id' => 'scala', 'text' => 'Scala (scala)'],
                                                                     20 => ['id' => 'swift', 'text' => 'Swift (swift)']]]];
                yield ['contests', $status, [$role], ['results' => [0 => ['id' => 2, 'text' => 'Demo contest (demo - c2)'],
                                                                    1 => ['id' => 1,
                                                                          'text' => 'Demo practice session (demoprac - c1)']
                                                                   ]]];
            }
        }
    }
}
