<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class AccessControllerTest extends BaseTest
{
    public function testAccess(): void
    {
        $url    = $this->helperGetEndpointURL('access');
        $access = $this->verifyApiJsonResponse('GET', $url, 200, 'admin');
        self::assertArrayHasKey('capabilities', $access);
        self::assertSame(
            ['contest_start', 'team_submit', 'team_clar', 'proxy_submit', 'proxy_clar', 'admin_submit', 'admin_clar'],
            $access['capabilities']
        );

        self::assertArrayHasKey('endpoints', $access);

        $expectedEndpoints = [
            [
                'type' => 'contest',
                'properties' => [
                    'id',
                    'name',
                    'formal_name',
                    'start_time',
                    'duration',
                    'scoreboard_freeze_duration',
                    'penalty_time',
                ],
            ],
            [
                'type' => 'judgement-types',
                'properties' => [
                    'id',
                    'name',
                    'penalty',
                    'solved',
                ],
            ],
            [
                'type' => 'languages',
                'properties' => [
                    'id',
                    'name',
                    'entry_point_required',
                    'entry_point_name',
                    'extensions',
                ],
            ],
            [
                'type' => 'problems',
                'properties' => [
                    'id',
                    'label',
                    'name',
                    'ordinal',
                    'rgb',
                    'color',
                    'time_limit',
                    'test_data_count',
                    'statement',
                ],
            ],
            [
                'type' => 'groups',
                'properties' => [
                    'id',
                    'icpc_id',
                    'name',
                    'hidden',
                ],
            ],
            [
                'type' => 'organizations',
                'properties' => [
                    'id',
                    'icpc_id',
                    'name',
                    'formal_name',
                    'country',
                    'country_flag',
                ],
            ],
            [
                'type' => 'teams',
                'properties' => [
                    'id',
                    'icpc_id',
                    'name',
                    'display_name',
                    'organization_id',
                    'group_ids',
                ],
            ],
            [
                'type' => 'state',
                'properties' => [
                    'started',
                    'frozen',
                    'ended',
                    'thawed',
                    'finalized',
                    'end_of_updates',
                ],
            ],
            [
                'type' => 'submissions',
                'properties' => [
                    'id',
                    'language_id',
                    'problem_id',
                    'team_id',
                    'time',
                    'contest_time',
                    'entry_point',
                    'files',
                ],
            ],
            [
                'type' => 'judgements',
                'properties' => [
                    'id',
                    'submission_id',
                    'judgement_type_id',
                    'start_time',
                    'start_contest_time',
                    'end_time',
                    'end_contest_time',
                    'max_run_time',
                ],
            ],
            [
                'type' => 'runs',
                'properties' => [
                    'id',
                    'judgement_id',
                    'ordinal',
                    'judgement_type_id',
                    'time',
                    'contest_time',
                    'run_time',
                ],
            ],
            [
                'type' => 'awards',
                'properties' => [
                    'id',
                    'citation',
                    'team_ids',
                ],
            ],
        ];

        self::assertSame($expectedEndpoints, $access['endpoints']);
    }
}
