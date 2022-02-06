<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class ExecutableControllerTest extends BaseTest
{
    /**
     * Test that a non-logged-in user can not access the executables.
     */
    public function testExecutablesNoAnonAccess(): void
    {
        $this->verifyApiResponse('GET', "/executables/compare", 401);
    }

    /**
     * Test that a team user can not access the executables.
     */
    public function testExecutablesNoTeamAccess(): void
    {
        $this->verifyApiResponse('GET', "/executables/compare", 403, 'demo');
    }

    /**
     * Test that a non-existent executable can not be fetched.
     */
    public function testExecutablesDoesNotExist(): void
    {
        $this->verifyApiResponse('GET', "/executables/volare", 404, 'admin');
    }

    /**
     * Test that an executable can be fetched.
     */
    public function testFetchExecutable(): void
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

        return $this->unzipString($decoded);
    }
}
