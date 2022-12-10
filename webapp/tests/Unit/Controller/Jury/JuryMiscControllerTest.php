<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleSubmissionsMultipleTriesFixture;
use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectFixture;
use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectSameLanguageFixture;
use App\DataFixtures\Test\DemoNonPublicContestFixture;
use App\DataFixtures\Test\DemoPostDeactivateContestFixture;
use App\DataFixtures\Test\DemoPreActivationContestFixture;
use App\DataFixtures\Test\DemoPreDeactivateContestFixture;
use App\DataFixtures\Test\DemoPreEndContestFixture;
use App\DataFixtures\Test\DemoPreFreezeContestFixture;
use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\DataFixtures\Test\DemoPreUnfreezeContestFixture;
use App\Entity\Contest;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class JuryMiscControllerTest extends BaseTest
{
    protected array $roles = ['jury'];

    /**
     * Test that if no user is logged in the user gets redirected to the login page.
     */
    public function testJuryRedirectToLogin(): void
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
     * @dataProvider provideContestStageForBalloon
     */
    public function testBalloonScoreboard(array $fixtures, bool $public, string $contestStage): void
    {
        //self::assertEquals((string) $public, $contestStage);
        $visibleElements = ["rank","team","Summary","C"];
        $nonActiveStages = ["preActivation","postDeactivate"];
        $this->loadFixtures($fixtures);
        /** @var ScoreboardService $sbs */
        $sbs = static::getContainer()->get(ScoreboardService::class);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $sbs->refreshCache($contest);
        $this->roles = ['balloon'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury', 200);
        $response = $this->client->getResponse();
        self::assertEquals('200', $response->getStatusCode());
        self::assertSelectorExists('body:contains("scoreboard")');
        foreach (['/public','/jury/scoreboard'] as $url) {
            $this->verifyPageResponse('GET', $url, 200);
            if (in_array($contestStage, $nonActiveStages) || (!$public && $url==='/public')) {
                $elements = ["No active contest"];
            } elseif ($contestStage === 'preStart') {
                $elements = ["scheduled to start on",'Demo contest','Utrecht University'];
            } elseif ($contestStage === 'preFreeze') {
                $elements = ["3 tries",'Demo contest','Utrecht University'];
            } elseif (in_array($contestStage, ['preEnd','preUnfreeze'])) {
                $elements = ["0 + 4 tries","3 tries","2 + 1 tries",'Demo contest','Utrecht University'];
                if ($contestStage === 'preFreeze') {
                    $elements[] = 'contest over, waiting for results';
                }
            } else {
                $elements = $visibleElements;
            }
            foreach ($elements as $selector) {
                self::assertSelectorExists('body:contains("'.$selector.'")');
            }
            if (in_array($contestStage, ['preFreeze','preEnd']) && $public) {
                self::assertSelectorExists('span.submcorrect:contains("1")');
                if (in_array($contestStage, ['preEnd','preUnfreeze'])) {
                    self::assertSelectorExists('span.submpend:contains("1")');
                    self::assertSelectorExists('span.submpend:contains("4")');
                    self::assertSelectorExists('span.submreject:contains("2")');
                    self::assertSelectorExists('span.submreject:contains("0")');
                }
            }
        }
        foreach (range(1, 3) as $id) {
            $statusCode = in_array($contestStage, ['preActivation','preStart','postDeactivate']) || !$public ? 404 : 200;
            $this->verifyPageResponse('HEAD', '/public/problems/'.$id.'/text', $statusCode);
        }
        $this->verifyPageResponse('GET', '/public/problems', 200);
        if (in_array($contestStage, array_merge(['preStart'], $nonActiveStages)) || !$public) {
            self::assertSelectorExists('body:contains("No problem texts available at this point.")');
        } else {
            self::assertSelectorNotExists('body:contains("No problem texts available at this point.")');
        }
    }

    public function provideContestStageForBalloon(): Generator
    {
        foreach (['preActivation'=>[DemoPreActivationContestFixture::class],
                  'preStart'=>[DemoPreStartContestFixture::class],
                  'preFreeze'=>[DemoPreFreezeContestFixture::class,SampleSubmissionsThreeTriesCorrectFixture::class],
                  'preEnd'=>[DemoPreEndContestFixture::class,
                             SampleSubmissionsMultipleTriesFixture::class,
                             SampleSubmissionsThreeTriesCorrectFixture::class,
                             SampleSubmissionsThreeTriesCorrectSameLanguageFixture::class],
                  'preUnfreeze'=>[DemoPreUnfreezeContestFixture::class,
                                  SampleSubmissionsMultipleTriesFixture::class,
                                  SampleSubmissionsThreeTriesCorrectFixture::class,
                                  SampleSubmissionsThreeTriesCorrectSameLanguageFixture::class],
                  'preDeactivate'=>[DemoPreDeactivateContestFixture::class,
                                    SampleSubmissionsMultipleTriesFixture::class,
                                    SampleSubmissionsThreeTriesCorrectFixture::class,
                                    SampleSubmissionsThreeTriesCorrectSameLanguageFixture::class],
                  'postDeactivate'=>[DemoPostDeactivateContestFixture::class,
                                     SampleSubmissionsMultipleTriesFixture::class,
                                     SampleSubmissionsThreeTriesCorrectFixture::class,
                                     SampleSubmissionsThreeTriesCorrectSameLanguageFixture::class]
            ] as $ident => $timeFixture) {
            foreach ([true,false] as $public) {
                $fixture = $public ? [] : [DemoNonPublicContestFixture::class];
                yield [array_merge($fixture, $timeFixture),$public, $ident];
            }
        }
    }

    /**
     * Test that the ajax endpoints return the correct data.
     *
     * @dataProvider provideJuryAjax
     */
    public function testJuryAjax(string $endpoint, int $status, array $newRoles, array $finalObject): void
    {
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
        foreach ([200 => ['balloon','jury','admin'], 403 => ['team']] as $status => $roles) {
            foreach ($roles as $role) {
                yield ['affiliations', $status, [$role], ['results' => [0 => ['id' => 1,
                                                                              'text' => 'Utrecht University (1)']
                                                                       ]]];
                yield ['locations', $status, [$role], ['results' => []]];
            }
        }
        foreach ([200 => ['jury','admin'], 403 => ['balloon','team']] as $status => $roles) {
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
                                                                     19 => ['id' => 'rs', 'text' => 'Rust (rs)'],
                                                                     20 => ['id' => 'scala', 'text' => 'Scala (scala)'],
                                                                     21 => ['id' => 'swift', 'text' => 'Swift (swift)']]]];
                yield ['contests', $status, [$role], ['results' => [0 => ['id' => 1, 'text' => 'Demo contest (demo - c1)']
                                                                   ]]];
            }
        }
    }
}
