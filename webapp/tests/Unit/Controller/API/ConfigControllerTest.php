<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;

class ConfigControllerTest extends BaseTest
{
    private string $endpoint = '/config';

    /**
     * Test that same public config variables are returned for all role types.
     *
     * @dataProvider provideUsers
     */
    public function testConfigReturnsPublicVariables(?string $user): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200, $user);

        static::assertIsArray($response);
        static::assertEquals(false, $response['score_in_seconds']);
        static::assertEquals(false, $response['compile_penalty']);
        static::assertEquals(100, $response['sourcefiles_limit']);
        static::assertEquals(2, $response['show_compile']);
        static::assertEquals(20, $response['penalty_time']);
        $categories = ['general' => 'General issue', 'tech' => 'Technical issue'];
        static::assertIsArray($response['clar_categories']);
        static::assertEquals($categories, $response['clar_categories']);
        static::assertIsArray($response['clar_queues']);
        static::assertCount(0, $response['clar_queues']);
    }

    /**
     * Test that secret config variables are not returned for non-admin.
     *
     * @dataProvider provideUnprivilegedUsers
     */
    public function testConfigDoesNotReturnSecretVariables(?string $user): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200, $user);

        static::assertIsArray($response);
        $secretvars = ['script_memory_limit', 'clar_answers', 'external_ccs_submission_url', 'data_source'];
        foreach ($secretvars as $secretvar) {
            static::assertArrayNotHasKey($secretvar, $response);
        }
    }

    public function testConfigReturnsSecretVariablesForAdmin(): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200, 'admin');

        static::assertIsArray($response);
        static::assertEquals(2097152, $response['script_memory_limit']);
        $answers = ['No comment.', 'Read the problem statement carefully.'];
        static::assertIsArray($response['clar_answers']);
        static::assertEquals($answers, $response['clar_answers']);
        static::assertEquals("", $response['external_ccs_submission_url']);
        static::assertEquals(0, $response['data_source']);
    }

    /**
     * Test that changing a config variable is reflected in the output,
     * and a different variable remains unchanged.
     */
    public function testConfigChangeVisible(): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200);

        static::assertIsArray($response);
        static::assertEquals(false, $response['compile_penalty']);
        static::assertEquals(20, $response['penalty_time']);

        $this->withChangedConfiguration('penalty_time', 100, function () {
            $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200);

            static::assertIsArray($response);
            static::assertEquals(false, $response['compile_penalty']);
            static::assertEquals(100, $response['penalty_time']);
        });
    }

    /**
     * Test that changing a config variable via the API works and is reflected in the output.
     */
    public function testConfigChangeAPIVisible(): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint, 200, 'admin');

        static::assertIsArray($response);
        static::assertEquals(false, $response['compile_penalty']);
        static::assertEquals(20, $response['penalty_time']);
        static::assertEquals(0, $response['data_source']);

        $proposedChange = ['compile_penalty' => true, 'penalty_time' => 21];
        $response = $this->verifyApiJsonResponse('PUT', $this->endpoint, 200, 'admin', $proposedChange);

        static::assertIsArray($response);
        static::assertEquals(true, $response['compile_penalty']);
        static::assertEquals(21, $response['penalty_time']);
        static::assertEquals(0, $response['data_source']);
    }

    /**
     * Test that invalid data is not accepted.
     */
    public function testConfigChangeAPIInvalidDataIsRejected(): void
    {
        $proposedChange = 'not an array';
        $this->verifyApiJsonResponse('PUT', $this->endpoint, 400, 'admin', $proposedChange);
    }

    /**
     * Test that anonymous and team users cannot change configuration.
     */
    public function testConfigChangeNotAllowedForUnprivilegedUsers(): void
    {
        $proposedChange = ['compile_penalty' => true, 'penalty_time' => 21];
        $this->verifyApiJsonResponse('PUT', $this->endpoint, 401, null, $proposedChange);
        $this->verifyApiJsonResponse('PUT', $this->endpoint, 403, 'demo', $proposedChange);
    }

    /**
     * Test that anonymous and team users cannot run the config checker.
     */
    public function testConfigCheckerNotAllowedForUnprivilegedUsers(): void
    {
        $this->verifyApiJsonResponse('GET', $this->endpoint . '/check', 401);
        $this->verifyApiJsonResponse('GET', $this->endpoint . '/check', 403, 'demo');
    }

    /**
     * Test the config checker endpoint returns expected content.
     */
    public function testConfigCheckerWorksForAdmin(): void
    {
        // In the test setup, the config check returns some errors so expected result is 260.
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint .'/check', 260, 'admin');

        $sections = ['System', 'Configuration', 'Contests', 'Problems and languages', 'Teams', 'External identifiers'];
        static::assertIsArray($response);
        static::assertEquals($sections, array_keys($response));

        foreach ($sections as $section) {
            static::assertIsArray($response[$section]);
            static::assertGreaterThan(0, count($response[$section]));
        }

        static::assertArrayHasKey('php_version', $response['System']);
        static::assertArrayHasKey('result', $response['System']['php_version']);
        static::assertEquals('O', $response['System']['php_version']['result']);

        static::assertArrayHasKey('languages', $response['Problems and languages']);
        static::assertArrayHasKey('caption', $response['Problems and languages']['languages']);
        static::assertEquals('Languages validation', $response['Problems and languages']['languages']['caption']);
        static::assertArrayHasKey('desc', $response['Problems and languages']['languages']);
        static::assertStringStartsWith('Validated all languages:', $response['Problems and languages']['languages']['desc']);
        static::assertStringContainsString('Language java: OK', $response['Problems and languages']['languages']['desc']);
        static::assertArrayHasKey('result', $response['Problems and languages']['languages']);
        static::assertEquals('O', $response['Problems and languages']['languages']['result']);
    }

    /**
     * Test that a specific variable can be requested and returns just this variable.
     */
    public function testConfigReturnsSpecificPublicVariable(): void
    {
        $response = $this->verifyApiJsonResponse('GET', $this->endpoint . '?name=penalty_time', 200);

        $expected = ['penalty_time' => 20];

        static::assertEquals($expected, $response);
    }

    /**
     * Test that a non-existent specific variable cannot be requested.
     */
    public function testConfigRequestNonExistentVariableThrowsError(): void
    {
        $this->verifyApiJsonResponse('GET', $this->endpoint . '?name=not_exist', 400);
    }

    public function provideUsers(): Generator
    {
        yield [null];
        yield ['demo'];
        yield ['admin'];
    }

    public function provideUnprivilegedUsers(): Generator
    {
        yield [null];
        yield ['demo'];
    }
}
