<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;

class AccountControllerTest extends AccountBaseTestCase
{
    protected ?string $apiEndpoint = 'accounts';

    protected ?string $apiUser = 'admin';

    protected array $expectedObjects = [
        'admin' => [
            "id"       => "admin",
            "username" => "admin",
            "team_id"  => "domjudge",
            "type"     => "admin",
            "ip"       => null,
        ],
        'judgehost' => [
            "id"       => "judgehost",
            "username" => "judgehost",
            "team_id"  => null,
            "type"     => "other",
            "ip"       => null,
        ],
        'demo' => [
            "id"       => "demo",
            "username" => "demo",
            "team_id"  => "exteam",
            "type"     => "team",
            "ip"       => null,
        ],
    ];

    /**
     * @dataProvider provideCurrentAccount
     */
    public function testCurrentAccount(string $user, array $expectedData): void
    {
        $url      = $this->helperGetEndpointURL('account');
        $response = $this->verifyApiJsonResponse('GET', $url, 200, $user);

        foreach ($expectedData as $key => $value) {
            self::assertArrayHasKey($key, $response, "$key is present");
            self::assertEquals($value, $response[$key], "$key has correct value");
        }
    }

    public function testCurrentAccountNotLoggedIn(): void
    {
        $url = $this->helperGetEndpointURL('account');
        $this->verifyApiJsonResponse('GET', $url, 404);
    }

    public function provideCurrentAccount(): Generator
    {
        yield ['admin', ['id' => 'admin', 'team_id' => 'domjudge', 'username' => 'admin', 'type' => 'admin']];
        yield ['demo', ['id' => 'demo', 'team_id' => 'exteam', 'username' => 'demo', 'type' => 'team']];
    }
}
