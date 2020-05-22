<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Controller\Jury\PrintController;
use App\Tests\BaseTest;

class PrintControllerTest extends BaseTest
{
    protected static $roles = ['jury'];

    /**
     * Test that by default printing is disabled
     */
    public function testPrintingDisabledJuryIndexPage()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(200, $response->getStatusCode(), $message);
        $this->assertEquals(0, $crawler->filter('a:contains("Print")')->count());
    }

    /**
     * Test that if printing is disabled, we get an access denied exception
     * when visiting the print page
     */
    public function testPrintingDisabledAccessDenied()
    {
        $this->logIn();
        $crawler = $this->client->request('GET', '/jury/print');

        $response = $this->client->getResponse();
        $message = var_export($response, true);
        $this->assertEquals(403, $response->getStatusCode(), $message);

    }

    /* In travis printing is currently not enabled, so tests are very
     * limited now. When more things moved to Symfony, we should vary
     * the configuration setting so we can also test the real thing.
     */
}
