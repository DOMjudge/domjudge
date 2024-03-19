<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Service\DOMJudgeService;

class UserControllerTest extends AccountBaseTestCase
{
    protected ?string $apiEndpoint = 'users';

    protected array $expectedObjects = [
        1 => [
            "team" => "DOMjudge",
            "roles" => [
                "admin",
                "team"
            ],
            "id" => "1",
            "username" => "admin",
            "name" => "Administrator",
            "email" => null,
            "ip" => null,
            "enabled" => true
        ],
        2 => [
            "team" => null,
            "roles" => [
                "judgehost"
            ],
            "id" => "2",
            "username" => "judgehost",
            "name" => "User for judgedaemons",
            "email" => null,
            "ip" => null,
            "enabled" => true
        ],
        3 => [
            "team" => "Example teamname",
            "roles" => [
                 "team"
            ],
            "id" => "3",
            "username" => "demo",
            "name" => "demo user for example team",
            "email" => null,
            "ip" => null,
            "enabled" => true
        ],
    ];

    public function testAddLocal(): void
    {
        $data = [
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('POST', $this->helperGetEndpointURL($this->apiEndpoint), 201, 'admin', $data);
        static::assertArrayHasKey('id', $response);
        static::assertEquals('testuser', $response['username']);
        static::assertEquals('Test User', $response['name']);
        static::assertEquals(['team'], $response['roles']);
    }

    public function testUpdateNonLocal(): void
    {
        $this->setupDataSource(DOMJudgeService::DATA_SOURCE_CONFIGURATION_EXTERNAL);
        $data = [
            'id' => 'someid',
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 201, 'admin', $data);
        static::assertEquals('someid', $response['id']);
        static::assertEquals('testuser', $response['username']);
        static::assertEquals('Test User', $response['name']);
        static::assertEquals(['team'], $response['roles']);
    }

    public function testUpdateNonLocalNoId(): void
    {
        $this->setupDataSource(DOMJudgeService::DATA_SOURCE_CONFIGURATION_EXTERNAL);
        $data = [
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 400, 'admin', $data);
        static::assertMatchesRegularExpression('/id:\n.*This value should be of type unknown./', $response['message']);
    }
}
