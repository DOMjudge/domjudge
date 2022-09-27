<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class AccessControllerTest extends BaseTest
{
    public function testAccessAsDemo(): void
    {
        $url = $this->helperGetEndpointURL('access');
        $this->verifyApiJsonResponse('GET', $url, 403, 'demo');
    }

    public function testAccessAsAdmin(): void
    {
        $url    = $this->helperGetEndpointURL('access');
        $access = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayHasKey('capabilities', $access);
        self::assertSame(
            ['contest_start', 'team_submit', 'team_clar', 'proxy_submit', 'proxy_clar', 'admin_submit', 'admin_clar'],
            $access['capabilities']
        );

        self::assertArrayHasKey('endpoints', $access);

        $expectedTypes = [
            'contest',
            'judgement-types',
            'languages',
            'problems',
            'groups',
            'organizations',
            'teams',
            'state',
            'submissions',
            'judgements',
            'runs',
            'awards',
        ];

        $actualTypes = array_map(fn(array $endpoint) => $endpoint['type'], $access['endpoints']);
        self::assertSame($expectedTypes, $actualTypes);
    }
}
