<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\EnableJavaEntrypointFixture;
use App\Service\DOMJudgeService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class LanguageControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'languages';

    protected array $expectedObjects = [
        'cpp'  => [
            'name'                 => 'C++',
            'entry_point_required' => false,
            'entry_point_name'     => null,
            'extensions'           => ['cpp', 'cc', 'cxx', 'c++'],
        ],
        'java' => [
            'name'                 => 'Java',
            'entry_point_required' => true,
            'entry_point_name'     => 'Main class',
            'extensions'           => ['java'],
        ],
    ];

    // Kotlin has allow_submit=false by default, so we don't expect it.
    protected array $expectedAbsent = ['kotlin', 'nonexistent'];

    protected static array $fixtures = [
        EnableJavaEntrypointFixture::class,
    ];

    public function testUpdateExecutable(): void {
        // First retrieve the before state.
        $response = $this->verifyApiJsonResponse('GET', '/languages/java', 200, 'admin');
        $before_hash = $response['compile_executable_hash'];

        // Create a ZIP file from some dummy content.
        $files = [
            'build' => '# Nothing to do',
            'run' => 'some dummy content',
            'Foo.java' => '// More dummy content',
        ];
        $zip = new ZipArchive();
        $dj = static::getContainer()->get(DOMJudgeService::class);
        $tempFilename = tempnam($dj->getDomjudgeTmpDir(), "api-languages-test-");

        $zip->open($tempFilename, ZipArchive::OVERWRITE);
        foreach ($files as $file => $content) {
            $zip->addFromString($file, $content);
        }
        $zip->close();

        // Now upload the newly created ZIP file.
        $zip = new UploadedFile($tempFilename, 'java.zip');
        $this->verifyApiJsonResponse('POST', '/languages/java/executable', 204, 'admin', null, ['executable' => $zip]);
        unlink($tempFilename);

        // Finally verify that updated hash is correct.
        $response = $this->verifyApiJsonResponse('GET', '/languages/java', 200, 'admin');
        $after_hash = '1be13faf2603b19c8bf5a398155a6d3b';
        static::assertEquals($after_hash, $response['compile_executable_hash']);

        // Make sure it has changed.
        static::assertNotEquals($before_hash, $after_hash);
    }
}
