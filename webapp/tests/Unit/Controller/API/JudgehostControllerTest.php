<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;

class JudgehostControllerTest extends BaseTest
{
    protected $apiEndpoint = 'judgehosts';
    protected $apiUser = 'admin';

    protected static $skipMessageCI = "This is very dependent on the contributor setup, check this in CI.";
    protected static $skipMessageIDs = "Filtering on IDs not implemented in this endpoint.";
    
    protected $expectedObjects = [];

    protected $expectedAbsent = ['4242', 'nonexistent'];

    public function testList(): void {
        if(getenv("CI")) {
            parent::testList();
        } else {
            static::markTestSkipped(static::$skipMessageCI);
        }
    }

    public function testListWithIds(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithIdsNotArray(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithAbsentIds(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function provideSingle(): Generator
    {
        foreach ($this->expectedObjects as $expectedProperties) {
            yield [$expectedProperties['hostname'], $expectedProperties];
        }
    }

    /**
     * Test that the endpoint returns an empty list for objects that don't exist
     *
     * @dataProvider provideSingleNotFound
     */
    public function testSingleNotFound(string $id): void {
        $id = $this->resolveReference($id);
        $url = $this->helperGetEndpointURL($this->apiEndpoint, $id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        static::assertEquals($object, []);
    }
}
