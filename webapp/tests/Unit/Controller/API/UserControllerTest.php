<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class UserControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'users';

    protected ?string $apiUser = 'admin';

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

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function testCreateUser(): void
    {
        $newUserPostData = ['username' => 'newUser',
                            'name' => 'newUserWithName',
                            'password' => 'xkcd-password-style-password',
                            'roles' => ['team']];

        $url = $this->helperGetEndpointURL($this->apiEndpoint);
        $this->verifyApiJsonResponse('POST', $url, 201, 'admin', $newUserPostData);
    }
}
