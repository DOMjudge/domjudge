<?php declare(strict_types=1);

namespace App\Tests\Unit;

use Generator;

class GeneralAvailabilityTest extends BaseTest
{
    /**
     * @dataProvider urlProvider
     */
    public function testPageIsSuccessful(string $url, int $code): void
    {
        if ($url === '/api/contests/1' && !$this->dataSourceIsLocal()) {
            // Use external ID for contest.
            $url = '/api/contests/demo';
        }

        $this->client->request('GET', $url);

        $response = $this->client->getResponse();
        $actual = $response->getStatusCode();
        self::assertEquals($code, $actual, var_export($response, true));
    }

    public function urlProvider(): Generator
    {
        yield ['/public/problems', 200];
        yield ['/public', 200];
        yield ['/login', 200];

        yield ['/api', 301]; // Gets redirected to /api/
        yield ['/api/', 200];
        yield ['/api/contests', 200];
        yield ['/api/contests/1', 200];
        // Note that the individual API endpoints are tested with check-api
        // and cannot easily be tested here since phpunit doesn't provide a
        // fully featured server environment.

        yield ['/', 302];
        yield ['/team', 302];
        yield ['/jury', 302];
        yield ['/logout', 302];

        yield ['/public/doesNotExist.php', 404];
    }
}
