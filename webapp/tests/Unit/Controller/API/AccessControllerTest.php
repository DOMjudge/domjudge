<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class AccessControllerTest extends BaseTest
{
    public function testAccessAsAnonymous(): void
    {
        $url = $this->helperGetEndpointURL('access');
        $this->verifyApiJsonResponse('GET', $url, 401);
    }

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
            'contest' => ['id', 'name', 'formal_name', 'start_time', 'duration', 'scoreboard_freeze_duration', 'penalty_time'],
            'judgement-types' => ['id', 'name', 'penalty', 'solved'],
            'languages' => ['id', 'name', 'entry_point_required', 'entry_point_name', 'extensions'],
            'problems' => ['id', 'label', 'name', 'ordinal', 'rgb', 'color', 'time_limit', 'test_data_count', 'statement'],
            'groups' => ['id', 'icpc_id', 'name', 'hidden'],
            'organizations' => ['id', 'icpc_id', 'name', 'formal_name', 'country', 'country_flag'],
            'teams' => ['id', 'icpc_id', 'name', 'display_name', 'organization_id', 'group_ids'],
            'state' => ['started', 'frozen', 'ended', 'thawed', 'finalized', 'end_of_updates'],
            'submissions' => ['id', 'language_id', 'problem_id', 'team_id', 'time', 'contest_time', 'entry_point', 'files'],
            'judgements' => ['id', 'submission_id', 'judgement_type_id', 'start_time', 'start_contest_time', 'end_time', 'end_contest_time', 'max_run_time'],
            'runs' => ['id', 'judgement_id', 'ordinal', 'judgement_type_id', 'time', 'contest_time', 'run_time'],
            'awards' => ['id', 'citation', 'team_ids'],
        ];

        $actualTypes = array_map(fn(array $endpoint) => $endpoint['type'], $access['endpoints']);
        self::assertSame(array_keys($expectedTypes), $actualTypes);

        foreach ($expectedTypes as $type => $expectedProperties) {
            $actualProperties = [];
            foreach ($access['endpoints'] as $res) {
                if ($res['type'] == $type) {
                    $actualProperties = $res['properties'];
                    break;
                }
            }
            self::assertSame($expectedProperties, $actualProperties);
        }
    }
}
