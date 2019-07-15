<?php declare(strict_types=1);

namespace Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GeneralAvailabilityTest extends WebTestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testPageIsSuccessful($url, $code)
    {
        $client = self::createClient();
        $client->request('GET', $url);

        $response = $client->getResponse();
        $actual = $response->getStatusCode();
        $this->assertEquals($code, $actual, var_export($response, true));
    }

    public function urlProvider()
    {
        yield ['/public/problems', 200];
        yield ['/public', 200];
        yield ['/login', 200];

        yield ['/api', 200];
        yield ['/api/contests', 200];
        yield ['/api/contests/2', 200];
        // Note that the individual API endpoints are tested with check-api
        // and cannot easily be tested here since phpunit doesn't provide a
        // fully featured server environment.

        yield ['/', 302];
        yield ['/team', 302];
        yield ['/jury', 302];
        yield ['/logout', 302];

        yield ['/public/doesnotexist.php', 404];
    }
}
