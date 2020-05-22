<?php declare(strict_types=1);

namespace App\Tests\Controller\Team;

use App\Tests\BaseTest;
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
        $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(302, $response->getStatusCode(), $message);
        $this->assertEquals('http://localhost/login', $response->getTargetUrl(),
            $message);
    }

    /**
     * Test the login process for teams
     */
    public function testLogin()
    {
        // Make sure the suer has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/team', 200);
    }

    /**
     * Test that the team overview page contains the correct data
     */
    public function testTeamOverviewPage()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);

        $this->assertSelectorExists('html:contains("Example teamname")');

        $h3s = $crawler->filter('h3')->extract(array('_text'));
        $this->assertEquals('Submissions', $h3s[0]);
        $this->assertEquals('Clarifications', $h3s[1]);
        $this->assertEquals('Clarification Requests', $h3s[2]);
    }

    /**
     * Test that by default printing is disabled
     */
    public function testPrintingDisabledTeamMenu()
    {
        $this->logIn();
        $this->client->request('GET', '/team');

        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);
        $this->assertSelectorNotExists('a:contains("Print")');
    }

    /**
     * Test that if printing is disabled, we get an access denied exception
     * when visiting the print page
     */
    public function testPrintingDisabledAccessDenied()
    {
        $this->logIn();
        $this->client->request('GET', '/team/print');

        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(403, $response->getStatusCode(), $message);
    }

    /**
     * Test that when printing is enabled the link is shown
     */
    public function testPrintingEnabledTeamMenu()
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->logIn();
                $this->client->request('GET', '/team');

                $response = $this->client->getResponse();
                $message  = var_export($response, true);
                $this->assertEquals(200, $response->getStatusCode(), $message);
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
                $this->logIn();
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
}
