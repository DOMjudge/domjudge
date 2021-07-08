<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Service\DOMJudgeService;
use App\Tests\Unit\BaseTest;
use Generator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Intl\Countries;

class GeneralInfoControllerTest extends BaseTest
{
    /**
     * Test that when a country flag exists, the correct data is returned
     *
     * @dataProvider provideCountryFlagExists
     */
    public function testCountryFlagExists(string $countryCode, string $size)
    {
        $this->client->request('GET', "/api/country-flags/$countryCode/$size.svg");
        /** @var BinaryFileResponse $response */
        $response = $this->client->getResponse();
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('image/svg+xml', $response->headers->get('Content-Type'));

        $svgFile = sprintf(
            '%s/public/flags/%s/%s.svg',
            static::$container->get(DOMJudgeService::class)->getDomjudgeWebappDir(),
            $size, strtolower(Countries::getAlpha2Code(strtoupper($countryCode)))
        );

        static::assertInstanceOf(BinaryFileResponse::class, $response);
        static::assertEquals($svgFile, $response->getFile()->getPathname());
    }

    public function provideCountryFlagExists(): Generator
    {
        yield ['NLD', '4x3'];
        yield ['DEU', '1x1'];
        yield ['gbr', '4x3']; // Also test case insensitivity
    }

    /**
     * Test that when a country flag does not exist, the correct message is returned
     *
     * @dataProvider provideCountryFlagNotFound
     */
    public function testCountryFlagNotFound(string $countryCode, string $size)
    {
        $this->client->request('GET', "/api/country-flags/$countryCode/$size.svg");
        /** @var BinaryFileResponse $response */
        $response = $this->client->getResponse();
        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('country flag not found', json_decode($response->getContent(), true)['message']);
    }

    public function provideCountryFlagNotFound(): Generator
    {
        yield ['ABC', '4x3'];
        yield ['XX', '1x1'];
        yield ['NLD', '2x2'];
        yield ['this is not a flag', '4x3'];
    }

    /**
     * Test that if flags are disable, no flag is returned
     *
     * @dataProvider provideCountryFlagExists
     */
    public function testCountryFlagDisabled(string $countryCode, string $size)
    {
        $this->withChangedConfiguration('show_flags', false, function () use ($countryCode, $size) {
            $this->client->request('GET', "/api/country-flags/$countryCode/$size.svg");
            /** @var BinaryFileResponse $response */
            $response = $this->client->getResponse();
            static::assertEquals(404, $response->getStatusCode());
            static::assertEquals('country flag not found', json_decode($response->getContent(), true)['message']);
        });
    }
}
