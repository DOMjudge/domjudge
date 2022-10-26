<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Controller\API\GeneralInfoController;
use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Service\DOMJudgeService;
use Generator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Intl\Countries;

class GeneralInfoControllerTest extends BaseTest
{
    private const API_VERSION = 4;

    public function testVersionReturnsApiVersion(): void
    {
        $response = $this->verifyApiJsonResponse('GET', "/version", 200);

        $expected = ['api_version' => static::API_VERSION];

        static::assertEquals($expected, $response);
    }

    /**
     * Test that both the API base as the info endpoint return the same data.
     */
    public function testInfoReturnsVariables(): void
    {
        $infoEndpoints = ['/', '/info'];

        foreach ($infoEndpoints as $endpoint) {
            $response = $this->verifyApiJsonResponse('GET', $endpoint, 200);

            static::assertIsArray($response);
            static::assertCount(4, $response);
            static::assertEquals(GeneralInfoController::CCS_SPEC_API_VERSION, $response['version']);
            static::assertEquals(GeneralInfoController::CCS_SPEC_API_URL, $response['version_url']);
            static::assertEquals('DOMjudge', $response['name']);
            static::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $response['domjudge']['version']);
            static::assertEquals('test', $response['domjudge']['environment']);
            static::assertStringStartsWith('http', $response['domjudge']['doc_url']);
        }
    }

    public function testStatusNoPublicAccess(): void
    {
        $this->verifyApiJsonResponse('GET', "/status", 401);
    }

    public function testStatusNoTeamAccess(): void
    {
        $this->verifyApiJsonResponse('GET', "/status", 403, 'demo');
    }

    /**
     * Test the basic output of the status endpoint without submissions present.
     */
    public function testStatusAdminBasicOperation(): void
    {
        $response = $this->verifyApiJsonResponse('GET', "/status", 200, 'admin');

        $expected = [[
            'num_submissions' => 0,
            'num_queued' => 0,
            'num_judging' => 0,
            'cid' => $this->getDemoContestId(),
        ]];

        static::assertEquals($expected, $response);
    }

    /**
     * Test that adding two submissions is reflected in the status endpoint.
     */
    public function testStatusAdminSubmissionsPresent(): void
    {
        $this->loadFixture(SampleSubmissionsFixture::class);
        $response = $this->verifyApiJsonResponse('GET', "/status", 200, 'admin');

        $expected = [[
            'num_submissions' => 2,
            'num_queued' => 2,
            'num_judging' => 0,
            'cid' => $this->getDemoContestId(),
        ]];

        static::assertEquals($expected, $response);
    }

    public function testUserEndpointMustBeLoggedIn(): void
    {
        $this->verifyApiJsonResponse('GET', "/status", 401);
    }

    /**
     * Test user endpoint with different users.
     * @dataProvider provideUsers
     */
    public function testUserEndpoint(string $username, string $fullname, string $teamname, array $roles): void
    {
        $response = $this->verifyApiJsonResponse('GET', "/user", 200, $username);

        static::assertEquals($username, $response['username']);
        static::assertEquals($fullname, $response['name']);
        static::assertEquals($teamname, $response['team']);
        static::assertEquals($roles, $response['roles']);
        static::assertTrue($response['enabled']);
        static::assertGreaterThanOrEqual($response['first_login_time'], $response['last_login_time']);
        $keysExpected = ['id', 'ip', 'last_ip', 'email'];
        foreach ($keysExpected as $keyExpected) {
            static::assertArrayHasKey($keyExpected, $response);
        }
    }

    public function provideUsers(): Generator
    {
        yield ['demo', 'demo user for example team', 'Example teamname', ['team']];
        yield ['admin', 'Administrator', 'DOMjudge', ['admin','team']];
    }

    /**
     * Test that when a country flag exists, the correct data is returned.
     *
     * @dataProvider provideCountryFlagExists
     */
    public function testCountryFlagExists(string $countryCode, string $size): void
    {
        $this->withChangedConfiguration('show_flags', true, function () use ($countryCode, $size) {
            $this->client->request('GET', "/api/country-flags/$countryCode/$size");
            /** @var BinaryFileResponse $response */
            $response = $this->client->getResponse();
            static::assertEquals(200, $response->getStatusCode());
            static::assertEquals('image/svg+xml', $response->headers->get('Content-Type'));

            $svgFile = sprintf(
                '%s/public/flags/%s/%s.svg',
                static::getContainer()->get(DOMJudgeService::class)->getDomjudgeWebappDir(),
                $size, strtolower(Countries::getAlpha2Code(strtoupper($countryCode)))
            );

            static::assertInstanceOf(BinaryFileResponse::class, $response);
            static::assertEquals($svgFile, $response->getFile()->getPathname());
        });
    }

    public function provideCountryFlagExists(): Generator
    {
        yield ['NLD', '4x3'];
        yield ['DEU', '1x1'];
        yield ['gbr', '4x3']; // Also test case insensitivity.
    }

    /**
     * Test that when a country flag of given size does not exist, the correct message is returned.
     *
     * @dataProvider provideCountryFlagSizeNotFound
     */
    public function testCountryFlagNotFound(string $countryCode, string $size): void
    {
        $this->withChangedConfiguration('show_flags', true, function () use ($countryCode, $size) {
            $this->client->request('GET', "/api/country-flags/$countryCode/$size");
            /** @var BinaryFileResponse $response */
            $response = $this->client->getResponse();
            static::assertEquals(404, $response->getStatusCode());
            static::assertEquals(sprintf('country flag for %s of size %s not found', strtoupper($countryCode), $size), json_decode($response->getContent(), true)['message']);
        });
    }

    public function provideCountryFlagSizeNotFound(): Generator
    {
        yield ['NLD', '2x2'];
        yield ['nld', '2x2'];
    }

    /**
     * Test that when a country does not exist, the correct message is returned.
     *
     * @dataProvider provideCountryFlagNotFound
     */
    public function testCountryNotFound(string $countryCode, string $size): void
    {
        $this->withChangedConfiguration('show_flags', true, function () use ($countryCode, $size) {
            $this->client->request('GET', "/api/country-flags/$countryCode/$size");
            /** @var BinaryFileResponse $response */
            $response = $this->client->getResponse();
            static::assertEquals(404, $response->getStatusCode());
            static::assertEquals(sprintf('country %s does not exist', strtoupper($countryCode)), json_decode($response->getContent(), true)['message']);
        });
    }

    public function provideCountryFlagNotFound(): Generator
    {
        yield ['ABC', '4x3'];
        yield ['XX', '1x1'];
        yield ['this is not a flag', '4x3'];
        yield ['THIS IS NOT A FLAG', '4x3'];
    }

    /**
     * Test that if flags are disabled, no flag is returned.
     *
     * @dataProvider provideCountryFlagExists
     */
    public function testCountryFlagDisabled(string $countryCode, string $size): void
    {
        $this->withChangedConfiguration('show_flags', false, function () use ($countryCode, $size) {
            $this->client->request('GET', "/api/country-flags/$countryCode/$size");
            /** @var BinaryFileResponse $response */
            $response = $this->client->getResponse();
            static::assertEquals(404, $response->getStatusCode());
            static::assertEquals('country flags disabled', json_decode($response->getContent(), true)['message']);
        });
    }
}
