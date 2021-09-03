<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;
use App\Service\DOMJudgeService;

class ExecutableControllerTest extends BaseTest
{
    /**
     * Test that a non logged in user can not access the executables
     */
    public function testExecutablesNoAnonAccess()
    {
        $this->verifyApiResponse('GET', "/executables/compare", 401);
    }

    /**
     * Test that a team user can not access the executables
     */
    public function testExecutablesNoTeamAccess()
    {
        $this->verifyApiResponse('GET', "/executables/compare", 403, 'demo');
    }

    /**
     * Test that a non-existent executable can not be fetched
     */
    public function testExecutablesDoesNotExist()
    {
        $this->verifyApiResponse('GET', "/executables/volare", 404, 'admin');
    }

    /**
     * Test that an executable can be fetched
     */
    public function testFetchExecutable()
    {
        $response = $this->verifyApiJsonResponse('GET', "/executables/compare", 200, 'admin');
        $contents = $this->base64unzip($response);

        static::assertArrayHasKey('build', $contents);
        static::assertStringContainsString('g++ -g', $contents['build']);
        static::assertArrayHasKey('compare.cc', $contents);
        static::assertStringContainsString("Space!  Can't live with it, can't live without it", $contents['compare.cc']);
    }

    protected function base64unzip(string $content): array
    {
        $decoded = base64_decode($content, true);

        $zip = new \ZipArchive();
        $tempFilename = tempnam(static::$container->get(DOMJudgeService::class)->getDomjudgeTmpDir(), "api-executables-test-");
        file_put_contents($tempFilename, $decoded);

        $zip->open($tempFilename);
        $return = [];
        for($i = 0; $i < $zip->count(); ++$i) {
            $return[$zip->getNameIndex($i)] = $zip->getFromIndex($i); 
        }
        $zip->close();

        unlink($tempFilename);
        return $return;
    }


}
