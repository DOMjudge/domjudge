<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;
use App\Tests\Unit\BaseTest as BaseBaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

abstract class BaseTest extends BaseBaseTest
{
    protected static array $rootEndpoints = ['contests', 'judgehosts', 'users'];

    /** @var KernelBrowser */
    protected KernelBrowser $client;

    /** The API endpoint to query for this test */
    protected ?string $apiEndpoint = null;

    /**
     * The username of the user to use for the tests.
     * Currently, only admin and demo are supported.
     * Leave set to null to use no user.
     */
    protected ?string $apiUser = null;

    /**
     * Fill this array with expected objects the API should return, indexed on ID.
     */
    protected array $expectedObjects = [];

    /** If the class to check uses external IDs in non-local mode, set the class name. */
    protected ?string $objectClassForExternalId = null;

    /**
     * When using a non-local data source this is used to look up external IDs.
     * Set it to an array where keys are fields and values are classes.
     *
     * @var string[]
     */
    protected array $entityReferences = [];

    /**
     * Fill this array with IDs of object that should not be present.
     *
     * @var string[]
     */
    protected array $expectedAbsent = [];
    protected Contest $demoContest;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the contest used in tests.
        $this->demoContest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)
            ->findOneBy(['shortname' => 'demo']);
    }

    /**
     * Verify the given API URL produces the given status code and return the body.
     *
     * @param string      $method   The HTTP method to use.
     * @param string      $apiUri   The API URL to use. Will be prefixed with /api.
     * @param int         $status   The status code to expect.
     * @param string|null $user     The username to use. Currently, only admin and demo are supported.
     * @param mixed       $jsonData The JSON data to set as a body, if any.
     * @param array       $files    The files to upload, if any.
     *
     * @return mixed The returned data
     */
    protected function verifyApiResponse(
        string $method,
        string $apiUri,
        int $status,
        ?string $user = null,
        $jsonData = null,
        array $files = []
    ) {
        $server = ['CONTENT_TYPE' => 'application/json'];
        // The API doesn't use cookie based logins, so we need to set a username/password.
        // For the admin, we can get it from initial_admin_password.secret and for the demo user we know it's 'demo'.
        if ($user === 'admin') {
            $adminPasswordFile = sprintf(
                '%s/%s',
                static::getContainer()->getParameter('domjudge.etcdir'),
                'initial_admin_password.secret'
            );
            $server['PHP_AUTH_USER'] = 'admin';
            $server['PHP_AUTH_PW'] = trim(file_get_contents($adminPasswordFile));
        } elseif ($user === 'demo') {
            // Team user
            $server['PHP_AUTH_USER'] = 'demo';
            $server['PHP_AUTH_PW'] = 'demo';
        } else {
            $server['PHP_AUTH_USER'] = $user;
            $server['PHP_AUTH_PW'] = $user;
        }
        $this->client->request($method, '/api' . $apiUri, [], $files, $server, $jsonData ? json_encode($jsonData) : null);
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        self::assertEquals($status, $response->getStatusCode(), $message);
        return $response->getContent();
    }

    /**
     * Verify the given API URL produces the given status code and return the body as JSON.
     *
     * @param string      $method   The HTTP method to use.
     * @param string      $apiUri   The API URL to use. Will be prefixed with /api.
     * @param int         $status   The status code to expect.
     * @param string|null $user     The username to use. Currently, only admin and demo are supported.
     * @param mixed       $jsonData The JSON data to set as a body, if any.
     * @param array       $files    The files to upload, if any.
     *
     * @return mixed The returned JSON data.
     */
    protected function verifyApiJsonResponse(
        string $method,
        string $apiUri,
        int $status,
        ?string $user = null,
        $jsonData = null,
        array $files = []
    ) {
        $response = $this->verifyApiResponse($method, $apiUri, $status, $user, $jsonData, $files);
        return json_decode($response, true);
    }

    public function helperGetEndpointURL(string $apiEndpoint, ?string $id = null): string
    {
        if (in_array($apiEndpoint, static::$rootEndpoints)) {
            $url = "/$apiEndpoint";
        } else {
            $contestId = $this->getDemoContestId();
            $url = "/contests/$contestId/$apiEndpoint";
        }
        if ($apiEndpoint === 'judgehosts') {
            return is_null($id) ? $url : "$url?hostname=$id";
        }
        return is_null($id) ? $url : "$url/$id";
    }

    /**
     * Test that the list action returns the expected objects.
     */
    public function testList(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint);
        $objects = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);

        static::assertIsArray($objects);
        foreach ($this->expectedObjects as $expectedObjectId => $expectedObject) {
            foreach ($this->entityReferences as $field => $class) {
                $expectedObject[$field] = $this->resolveEntityId($class, $expectedObject[$field]);
            }

            $expectedObjectId = $this->resolveReference($expectedObjectId);
            if ($this->objectClassForExternalId !== null) {
                $expectedObjectId = $this->resolveEntityId($this->objectClassForExternalId, (string)$expectedObjectId);
            }
            $object = null;
            foreach ($objects as $potentialObject) {
                if ($potentialObject['id'] == $expectedObjectId) {
                    $object = $potentialObject;
                    break;
                }
            }

            static::assertNotNull($object);
            static::assertEquals($expectedObjectId, $object['id']);
            foreach ($expectedObject as $key => $value) {
                // Null values can also be absent.
                static::assertEquals($value, $object[$key] ?? null, $key . ' has correct value.');
            }
        }

        foreach ($this->expectedAbsent as $expectedAbsentId) {
            $object = null;
            foreach ($objects as $potentialObject) {
                if ($potentialObject['id'] == $expectedAbsentId) {
                    $object = $potentialObject;
                    break;
                }
            }

            static::assertNull($object);
        }
    }

    /**
     * Test that the list action returns the expected objects if IDs are passed.
     */
    public function testListWithIds(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $expectedObjectIds = array_map(function ($id) {
            $id = $this->resolveReference($id);
            if ($this->objectClassForExternalId !== null) {
                $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
            }
            return $id;
        }, array_keys($this->expectedObjects));
        $url = $this->helperGetEndpointURL($apiEndpoint);
        $objects = $this->verifyApiJsonResponse('GET', $url . "?" . http_build_query(['ids' => $expectedObjectIds]), 200, $this->apiUser);

        // Verify we got exactly enough objects.
        static::assertIsArray($objects);
        static::assertCount(count($this->expectedObjects), $objects);

        // Verify all objects are present.
        foreach ($expectedObjectIds as $expectedObjectId) {
            $object = null;
            foreach ($objects as $potentialObject) {
                if ($potentialObject['id'] == $expectedObjectId) {
                    $object = $potentialObject;
                    break;
                }
            }

            static::assertNotNull($object);
        }
    }

    /**
     * Test that the list action returns the correct error when the contest is not found.
     */
    public function testListContestNotFound(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        if (in_array($apiEndpoint, static::$rootEndpoints)) {
            // We can not test this, since e.g. /contests always exists. Assert that true is true to not make the test
            // risky.
            static::assertTrue(true);
            return;
        }
        // Note that the 42 here is a contest that doesn't exist.
        $response = $this->verifyApiJsonResponse('GET', "/contests/42/$apiEndpoint", 404, $this->apiUser);
        static::assertEquals('Contest with ID \'42\' not found', $response['message']);
    }

    /**
     * Test that the list method returns the correct error when the IDs parameter is not an array.
     */
    public function testListWithIdsNotArray(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint);
        $response = $this->verifyApiJsonResponse('GET', $url . "?" . http_build_query(['ids' => 2]), 400, $this->apiUser);
        static::assertEquals('Unexpected value for parameter "ids": expecting "array", got "string".', $response['message']);
    }

    /**
     * Test that the list method returns the correct error when passing IDs that don't exist.
     */
    public function testListWithAbsentIds(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }

        $expectedObjectIds = array_map(function ($id) {
            return $this->resolveReference($id);
        }, array_keys($this->expectedObjects));
        $ids = array_merge($expectedObjectIds, $this->expectedAbsent);
        $url = $this->helperGetEndpointURL($apiEndpoint);
        $response = $this->verifyApiJsonResponse('GET', $url . "?" . http_build_query(['ids' => $ids]), 404, $this->apiUser);
        static::assertEquals('One or more objects not found', $response['message']);
    }

    /**
     * Test that the single action returns the correct data.
     *
     * @dataProvider provideSingle
     */
    public function testSingle($id, array $expectedProperties): void
    {
        foreach ($this->entityReferences as $field => $class) {
            $expectedProperties[$field] = $this->resolveEntityId($class, $expectedProperties[$field]);
        }

        $id = $this->resolveReference($id);
        if ($this->objectClassForExternalId !== null) {
            $id = $this->resolveEntityId($this->objectClassForExternalId, (string)$id);
        }
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint, (string)$id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        static::assertIsArray($object);

        $object = is_array($object) && count($object)===1 ? $object[0] : $object;
        foreach ($expectedProperties as $key => $value) {
            // Null values can also be absent.
            static::assertEquals($value, $object[$key] ?? null, $key . ' has correct value.');
        }
    }

    public function provideSingle(): Generator
    {
        foreach ($this->expectedObjects as $id => $expectedProperties) {
            yield [$id, $expectedProperties];
        }
    }

    /**
     * Test that the single action returns the correct error when the contest is not found.
     */
    public function testSingleContestNotFound(): void
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        if (in_array($apiEndpoint, static::$rootEndpoints)) {
            static::markTestSkipped('Endpoint does not have contests prefix.');
        }

        // Note that the 42 here is a contest that doesn't exist.
        $url = "/contests/42/$apiEndpoint/123";
        $message = 'Contest with ID \'42\' not found';

        $response = $this->verifyApiJsonResponse('GET', $url, 404, $this->apiUser);
        static::assertEquals($message, $response['message']);
    }

    /**
     * Test that the endpoint does not return anything for objects that don't exist.
     *
     * @dataProvider provideSingleNotFound
     */
    public function testSingleNotFound(string $id): void
    {
        $id = $this->resolveReference($id);
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint, $id);
        $this->verifyApiJsonResponse('GET', $url, 404, $this->apiUser);
    }

    public function provideSingleNotFound(): Generator
    {
        foreach ($this->expectedAbsent as $id) {
            yield [$id];
        }
    }
}
