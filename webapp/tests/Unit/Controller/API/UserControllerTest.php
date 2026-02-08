<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class UserControllerTest extends AccountBaseTestCase
{
    protected ?string $apiEndpoint = 'users';

    protected array $expectedObjects = [
        'admin' => [
            "team" => "DOMjudge",
            "roles" => [
                "admin",
                "team"
            ],
            "id" => "admin",
            "username" => "admin",
            "name" => "Administrator",
            "ip" => null,
            "enabled" => true
        ],
        'judgehost' => [
            "team" => null,
            "roles" => [
                "judgehost"
            ],
            "id" => "judgehost",
            "username" => "judgehost",
            "name" => "User for judgedaemons",
            "ip" => null,
            "enabled" => true
        ],
        'demo' => [
            "team" => "Example teamname",
            "roles" => [
                 "team"
            ],
            "id" => "demo",
            "username" => "demo",
            "name" => "demo user for example team",
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

    public function testUpdate(): void
    {
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

    public function testUpdateNoId(): void
    {
        $data = [
            'username' => 'testuser',
            'name' => 'Test User',
            'roles' => ['team'],
            'password' => 'testpassword',
        ];

        $response = $this->verifyApiJsonResponse('PUT', $this->helperGetEndpointURL($this->apiEndpoint) . '/someid', 400, 'admin', $data);
        static::assertMatchesRegularExpression('/id:\n.*This value should be of type string./', $response['message']);
    }
}
