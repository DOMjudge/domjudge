<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PrintControllerTest extends BaseTest
{
    protected array $roles = ['jury'];

    protected const PRINT_COMMAND = 'echo [language] && /bin/cat [file]';

    /**
     * Test that by default printing is disabled.
     */
    public function testPrintingDisabledJuryIndexPage(): void
    {
        $this->verifyPageResponse('GET', '/jury', 200);
        self::assertSelectorNotExists('a:contains("Print")');
    }

    /**
     * Test that if printing is disabled, we get access denied exception
     * when visiting the print page.
     */
    public function testPrintingDisabledAccessDenied(): void
    {
        $this->verifyPageResponse('GET', '/jury/print', 403);
    }

    /**
     * Test that when printing is enabled the link is shown.
     */
    public function testPrintingEnabledJuryIndexPage(): void
    {
        $this->withChangedConfiguration('print_command', static::PRINT_COMMAND,
            function () {
                $this->verifyPageResponse('GET', '/jury', 200);
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
                $this->verifyPageResponse('GET', '/jury/print', 200);

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
