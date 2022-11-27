<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Entity\Contest;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MiscControllerTest extends BaseTest
{
    protected array $roles = ['team'];

    private const PRINT_COMMAND = 'echo [language] && /bin/cat [file]';

    public function testTeamRedirectToLogin(): void
    {
        // Log out, we are testing public functionality.
        $this->logOut();

        $this->verifyPageResponse('GET', '/team', 302, 'http://localhost/login');
    }

    /**
     * Test the login process for teams.
     */
    public function testLogin(): void
    {
        // Log out, we are testing log in functionality.
        $this->logOut();

        // Make sure the user has the correct permissions.
        $this->setupUser();

        // Test incorrect and correct password.
        $this->loginHelper('demo', 'foo', 'http://localhost/login', 401);
        $this->loginHelper('demo', 'demo', 'http://localhost/team', 200);
    }

    /**
     * Test that the team overview page contains the correct data for normal
     * and AJAX requests.
     *
     * @dataProvider ajaxProvider
     */
    public function testTeamOverviewPage(bool $ajax): void
    {
        $this->verifyPageResponse('GET', '/team', 200, null, $ajax);

        self::assertSelectorExists('html:contains("Example teamname")');

        $h1s = $this->getCurrentCrawler()->filter('h1')->extract(array('_text'));
        self::assertEquals('Submissions', $h1s[0]);
        self::assertEquals('Clarifications', $h1s[1]);
        self::assertEquals('Clarification Requests', $h1s[2]);
    }

    public function ajaxProvider(): Generator
    {
        yield [false];
        yield [true];
    }

    public function testPrintingDisabledTeamMenu(): void
    {
        $this->verifyPageResponse('GET', '/team', 200);
        self::assertSelectorNotExists('a:contains("Print")');
    }

    /**
     * Test that if printing is disabled, we get access denied exception.
     * when visiting the print page.
     */
    public function testPrintingDisabledAccessDenied(): void
    {
        $this->verifyPageResponse('GET', '/team/print', 403);
    }

    /**
     * Test that when printing is enabled the link is shown.
     */
    public function testPrintingEnabledTeamMenu(): void
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->verifyPageResponse('GET', '/team', 200);
                $this->assertSelectorExists('a:contains("Print")');
            });
    }

    /**
     * Test that if printing is enabled, we can actually print something.
     */
    public function testPrintingEnabledSubmitForm(): void
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
     * Test that it is possible to change contests.
     *
     * @dataProvider withReferrerProvider
     */
    public function testChangeContest(bool $withReferrer): void
    {
        $start       = (int)floor(microtime(true) - 1800);
        $startString = date('Y-m-d H:i:s ',
                $start) . date_default_timezone_get();

        // Create a second contest.
        $contest = new Contest();
        $contest
            ->setName('Test contest for switching')
            ->setShortname('switch')
            ->setStarttimeString($startString)
            ->setStarttime($start)
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+05:00')
            ->setFreezetimeString('+04:00');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($contest);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/scoreboard');

        // TODO: Enable again when unit tests are rewritten
        $this->markTestSkipped('Needs to be rewritten to handle different DB states.');
        // Verify we are on the demo contest.
        self::assertSelectorTextContains('.card-header span', 'Demo contest');

        if ($withReferrer) {
            // Now click the change contest menu item.
            $link = $crawler->filter('a.dropdown-item:contains("switch")')->link();

            $this->client->click($link);
        } else {
            // Make sure to clear the history, so we do not have a referrer.
            $this->client->getHistory()->clear();
            $this->client->request('GET', '/team/change-contest/' . $contest->getCid());
        }

        $this->client->followRedirect();

        // Check that we are still on the scoreboard.
        if ($withReferrer) {
            self::assertEquals('http://localhost/team/scoreboard',
                               $this->client->getRequest()->getUri());
        } else {
            self::assertEquals('http://localhost/team',
                               $this->client->getRequest()->getUri());

            // Go to the scoreboard again.
            $this->client->request('GET', '/team/scoreboard');
        }

        // And check that the contest has changed.
        self::assertSelectorTextContains('.card-header span', 'Test contest for switching');
    }

    public function withReferrerProvider(): Generator
    {
        yield [true];
        yield [false];
    }

    /**
     * Test that no docs.yaml does not show docs link.
     */
    public function testDocsNoDocs(): void
    {
        $this->verifyPageResponse('GET', '/team', 200);

        self::assertSelectorNotExists('a:contains("Docs")');
    }
}
