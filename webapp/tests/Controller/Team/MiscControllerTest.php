<?php declare(strict_types=1);

namespace App\Tests\Controller\Team;

use App\Entity\Contest;
use App\Tests\BaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MiscControllerTest extends BaseTest
{
    protected static $roles = ['team'];

    const PRINT_COMMAND = 'echo [language] && /bin/cat [file]';

    /**
     * Test that if no user is logged in the user gets redirected to the login page
     */
    public function testTeamRedirectToLogin()
    {
        // Log out, we are testing public functionality
        $this->logOut();

        $this->verifyPageResponse('GET', '/team', 302, 'http://localhost/login');
    }

    /**
     * Test the login process for teams
     */
    public function testLogin()
    {
        // Log out, we are testing log in functionality
        $this->logOut();

        // Make sure the user has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/team', 200);
    }

    /**
     * Test that the team overview page contains the correct data for normal
     * and AJAX requests
     *
     * @dataProvider ajaxProvider
     *
     * @param bool $ajax
     */
    public function testTeamOverviewPage(bool $ajax)
    {
        $this->verifyPageResponse('GET', '/team', 200, null, $ajax);

        $this->assertSelectorExists('html:contains("Example teamname")');

        $h1s = $this->getCurrentCrawler()->filter('h1')->extract(array('_text'));
        $this->assertEquals('Submissions', $h1s[0]);
        $this->assertEquals('Clarifications', $h1s[1]);
        $this->assertEquals('Clarification Requests', $h1s[2]);
    }

    public function ajaxProvider()
    {
        yield [false];
        yield [true];
    }

    /**
     * Test that by default printing is disabled
     */
    public function testPrintingDisabledTeamMenu()
    {
        $this->verifyPageResponse('GET', '/team', 200);
        $this->assertSelectorNotExists('a:contains("Print")');
    }

    /**
     * Test that if printing is disabled, we get an access denied exception
     * when visiting the print page
     */
    public function testPrintingDisabledAccessDenied()
    {
        $this->verifyPageResponse('GET', '/team/print', 403);
    }

    /**
     * Test that when printing is enabled the link is shown
     */
    public function testPrintingEnabledTeamMenu()
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->verifyPageResponse('GET', '/team', 200);
                $this->assertSelectorExists('a:contains("Print")');
            });
    }

    /**
     * Test that if printing is enabled, we can actually print something
     */
    public function testPrintingEnabledSubmitForm()
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->client->request('GET', '/team/print');

                $testFile = __DIR__ . '/MiscControllerTest.php';
                $code     = new UploadedFile($testFile, 'test.kt');

                $crawler = $this->client->submitForm('Print code', [
                    'print[code]' => $code,
                    'print[langid]' => 'kt',
                ]);

                $this->assertSelectorTextContains('div.alert.alert-success',
                    'File has been printed');

                $text = trim($crawler->filter('pre')->text(null, false));
                $this->assertStringStartsWith('kt', $text);
                $this->assertStringEndsWith(
                    trim(file_get_contents($testFile)), $text);
            });
    }

    /**
     * Test that it is possible to change contests
     *
     * @param bool $withReferrer
     *
     * @dataProvider withReferrerProvider
     */
    public function testChangeContest(bool $withReferrer)
    {
        $start       = (int)floor(microtime(true) - 1800);
        $startString = strftime('%Y-%m-%d %H:%M:%S ',
                $start) . date_default_timezone_get();

        // Create a second contest
        $contest = new Contest();
        $contest
            ->setName('Test contest for switching')
            ->setShortname('switch')
            ->setStarttimeString($startString)
            ->setStarttime($start)
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+05:00')
            ->setFreezetimeString('+04:00');

        $em = self::$container->get(EntityManagerInterface::class);
        $em->persist($contest);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/scoreboard');

        // Verify we are on the demo contest
        $this->assertSelectorTextContains('.card-header span', 'Demo contest');

        if ($withReferrer) {
            // Now click the change contest menu item
            $link = $crawler->filter('a.dropdown-item:contains("switch")')->link();

            $this->client->click($link);
        } else {
            // Make sure to clear the history so we do not have a referrer
            $this->client->getHistory()->clear();;
            $this->client->request('GET', '/team/change-contest/' . $contest->getCid());
        }

        $this->client->followRedirect();

        // Check that we are still on the scoreboard
        if ($withReferrer) {
            $this->assertEquals('http://localhost/team/scoreboard',
                $this->client->getRequest()->getUri());
        } else {
            $this->assertEquals('http://localhost/team',
                $this->client->getRequest()->getUri());

            // Go to the scoreboard again
            $this->client->request('GET', '/team/scoreboard');
        }

        // And check that the contest has changed
        $this->assertSelectorTextContains('.card-header span', 'Test contest for switching');

        // Remove the contest again
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'switch']);
        $em->remove($contest);
        $em->flush();
    }

    public function withReferrerProvider()
    {
        yield [true];
        yield [false];
    }
}
