<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class UserControllerTest extends BaseTest
{
    protected $apiEndpoint = 'users';

    protected $apiUser = 'admin';

    protected $expectedObjects = [
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
            "last_ip" => "127.0.0.1",
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
            "last_ip" => "127.0.0.1",
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
            "last_ip" => null,
            "ip" => null,
            "enabled" => true
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];

}
