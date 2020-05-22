<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PrintControllerTest extends BaseTest
{
    protected static $roles = ['jury'];

    const PRINT_COMMAND = 'echo [language] && /bin/cat [file]';

    /**
     * Test that by default printing is disabled
     */
    public function testPrintingDisabledJuryIndexPage()
    {
        $this->logIn();
        $this->client->request('GET', '/jury');

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
        $this->client->request('GET', '/jury/print');

        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        $this->assertEquals(403, $response->getStatusCode(), $message);
    }

    /**
     * Test that when printing is enabled the link is shown
     */
    public function testPrintingEnabledJuryIndexPage()
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->logIn();
                $this->client->request('GET', '/jury');

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
                $this->client->request('GET', '/jury/print');

                $testFile = __DIR__ . '/PrintControllerTest.php';
                $code     = new UploadedFile($testFile, 'test.cs');

                $crawler = $this->client->submitForm('Print code', [
                    'print[code]' => $code,
                    'print[langid]' => 'csharp',
                ]);

                $this->assertSelectorTextContains('div.alert.alert-success',
                    'File has been printed');

                $text = trim($crawler->filter('pre')->text(null, false));
                $this->assertStringStartsWith('csharp', $text);
                $this->assertStringEndsWith(
                    trim(file_get_contents($testFile)), $text);
            });
    }
}
