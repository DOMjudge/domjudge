<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class TeamControllerTest extends BaseTest
{
    protected $apiEndpoint = 'teams';

    protected $expectedObjects = [
        '2' => [
            'organization_id' => '1',
            'group_ids'       => ['3'],
            'affiliation'     => 'Utrecht University',
            'nationality'     => 'NLD',
            'id'              => '2',
            'icpc_id'         => 'exteam',
            'name'            => 'Example teamname',
            'display_name'    => null,
            'members'         => null
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];
}
