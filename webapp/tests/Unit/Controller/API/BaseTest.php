<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;
use App\Tests\Unit\BaseTest as BaseBaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PropertyAccess\PropertyAccess;

abstract class BaseTest extends BaseBaseTest
{
    /** @var KernelBrowser */
    protected $client;

    /**
     * The API endpoint to query for this test
     *
     * @var string|null
     */
    protected $apiEndpoint = null;

    /**
     * The username of the user to use for the tests.
     * Currently only admin and demo are supported.
     * Leave set to null to use no user.
     *
     * @var string|null
     */
    protected $apiUser = null;

    /**
     * Fill this array with expected objects the API should return, indexed on ID
     *
     * @var mixed[]
     */
    protected $expectedObjects = [];

    /**
     * Fill this array with ID's of object that should not be present
     *
     * @var string[]
     */
    protected $expectedAbsent = [];

    /**
     * @var Contest
     */
    protected $demoContest;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the contest used in tests
        $this->demoContest = static::$container->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)
            ->findOneBy(['shortname' => 'demo']);
    }

    /**
     * Verify the given API URL produces the given status code and return the body as JSON
     *
     * @param string      $method   The HTTP method to use
     * @param string      $apiUri   The API URL to use. Will be prefixed with /api
     * @param int         $status   The status code to expect
     * @param string|null $user     The username to use. Currently only admin and demo are supported
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
        // For the admin, we can get it from initial_admin_password.secret and for the demo user we know it's 'demo'
        if ($user === 'admin') {
            $adminPasswordFile = sprintf(
                '%s/%s',
                static::$container->getParameter('domjudge.etcdir'),
                'initial_admin_password.secret'
            );
            $server['PHP_AUTH_USER'] = 'admin';
            $server['PHP_AUTH_PW'] = trim(file_get_contents($adminPasswordFile));
        } elseif ($user === 'demo') {
            // Team user
            $server['PHP_AUTH_USER'] = 'demo';
            $server['PHP_AUTH_PW'] = 'demo';
        }
        $this->client->request($method, '/api' . $apiUri, [], $files, $server, $jsonData ? json_encode($jsonData) : null);
        $response = $this->client->getResponse();
        $message = var_export($response, true);
        self::assertEquals($status, $response->getStatusCode(), $message);
        return json_decode($response->getContent(), true);
    }

    /**
     * Test that the list action returns the expected objects
     */
    public function testList()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();
        $objects = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint", 200, $this->apiUser);

        static::assertIsArray($objects);
        foreach ($this->expectedObjects as $expectedObjectId => $expectedObject) {
            $expectedObjectId = $this->resolveReference($expectedObjectId);
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
                static::assertEquals($value, $object[$key]);
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
     * Test that the list action returns the expected objects if ID's are passed
     */
    public function testListWithIds()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();
        $expectedObjectIds = array_map(function ($id) {
            return $this->resolveReference($id);
        }, array_keys($this->expectedObjects));
        $objects = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint?" . http_build_query(['ids' => $expectedObjectIds]), 200, $this->apiUser);

        // Verify we got exactly enough objects
        static::assertIsArray($objects);
        static::assertCount(count($this->expectedObjects), $objects);

        // Verify all objects are present
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
     * Test that the list action returns the correct error when the contest is not found
     */
    public function testListContestNotFound()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        // Note that the 42 here is a contest that doesn't exist
        $respone = $this->verifyApiJsonResponse('GET', "/contests/42/$apiEndpoint", 404, $this->apiUser);
        static::assertEquals('Contest with ID \'42\' not found', $respone['message']);
    }

    /**
     * Test that the list method returns the correct error when the ids parameter is not an array
     */
    public function testListWithIdsNotArray()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();
        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint?" . http_build_query(['ids' => 2]), 400, $this->apiUser);
        static::assertEquals("'ids' should be an array of ID's to fetch", $response['message']);
    }

    /**
     * Test that the list method returns the correct error when passing ID's that don't exist
     */
    public function testListWithAbsentIds()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();

        $expectedObjectIds = array_map(function ($id) {
            return $this->resolveReference($id);
        }, array_keys($this->expectedObjects));
        $ids = array_merge($expectedObjectIds, $this->expectedAbsent);
        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint?" . http_build_query(['ids' => $ids]), 404, $this->apiUser);
        static::assertEquals('One or more objects not found', $response['message']);
    }

    /**
     * Test that the single action returns the correct data
     *
     * @dataProvider provideSingle
     */
    public function testSingle($id, array $expectedProperties)
    {
        $id = $this->resolveReference($id);
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();
        $object = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$id", 200, $this->apiUser);
        static::assertIsArray($object);

        foreach ($expectedProperties as $key => $value) {
            static::assertEquals($value, $object[$key]);
        }
    }

    public function provideSingle(): Generator
    {
        foreach ($this->expectedObjects as $id => $expectedProperties) {
            yield [$id, $expectedProperties];
        }
    }

    /**
     * Test that the single action returns the correct error when the contest is not found
     */
    public function testSingleContestNotFound()
    {
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        // Note that the 42 here is a contest that doesn't exist
        $respone = $this->verifyApiJsonResponse('GET', "/contests/42/$apiEndpoint/123", 404, $this->apiUser);
        static::assertEquals('Contest with ID \'42\' not found', $respone['message']);
    }

    /**
     * Test that the endpoint does not return anything for objects that don't exist
     *
     * @dataProvider provideSingleNotFound
     */
    public function testSingleNotFound(string $id)
    {
        $id = $this->resolveReference($id);
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined');
        }
        $contestId = $this->demoContest->getCid();
        $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$id", 404, $this->apiUser);
    }

    public function provideSingleNotFound(): Generator
    {
        foreach ($this->expectedAbsent as $id) {
            yield [$id];
        }
    }

    /**
     * Resolve any references in the given ID
     */
    protected function resolveReference($id)
    {
        // If the object ID contains a :, it is a reference to a fixture item, so get it
        if (is_string($id) && strpos($id, ':') !== false) {
            $referenceObject = $this->fixtureExecutor->getReferenceRepository()->getReference($id);
            $metadata = static::$container->get(EntityManagerInterface::class)->getClassMetadata(get_class($referenceObject));
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            return $propertyAccessor->getValue($referenceObject, $metadata->getSingleIdentifierColumnName());
        }

        return $id;
    }
}
