<?php declare(strict_types=1);

namespace App\Tests\Controller\API;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BaseTest extends WebTestCase
{
    /** @var KernelBrowser */
    protected $client;

    protected function setUp(): void
    {
        // Reset the kernel to make sure we have a clean slate
        self::ensureKernelShutdown();

        // Create a client to communicate with the application
        $this->client = self::createClient();
    }

    /**
     * Verify the given API URL produces the given status code and return the body as JSON
     *
     * @param string      $method   The HTTP method to use
     * @param string      $apiUri   The API URL to use. Will be prefixed with /api
     * @param int         $status   The status code to expect
     * @param string|null $user     The username to use. Currently only admin and dummy are supported
     * @param mixed       $jsonData The JSON data to set as a body, if any
     * @param array       $files    The files to upload, if any
     *
     * @return mixed The returned JSON data
     */
    protected function verifyApiJsonResponse(
        string $method,
        string $apiUri,
        int $status,
        ?string $user = null,
        $jsonData = null,
        array $files = []
    ) {
        $server = ['CONTENT_TYPE' => 'application/json'];
        // The API doesn't use cookie based logins, so we need to set a username/password.
        // For the admin, we can get it from initial_admin_password.secret and for the dummy user we know it's 'dummy'
        if ($user === 'admin') {
            $adminPasswordFile       = sprintf(
                '%s/%s',
                static::$container->getParameter('domjudge.etcdir'),
                'initial_admin_password.secret'
            );
            $server['PHP_AUTH_USER'] = 'admin';
            $server['PHP_AUTH_PW']   = trim(file_get_contents($adminPasswordFile));
        } elseif ($user === 'dummy') {
            // Team user
            $server['PHP_AUTH_USER'] = 'dummy';
            $server['PHP_AUTH_PW']   = 'dummy';
        }
        $this->client->request($method, '/api' . $apiUri, [], $files, $server, $jsonData ? json_encode($jsonData) : null);
        $response = $this->client->getResponse();
        $message  = var_export($response, true);
        self::assertEquals($status, $response->getStatusCode(), $message);
        return json_decode($response->getContent(), true);
    }
}
