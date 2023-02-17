<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SampleSubmissionsFixture;

class MetricsControllerTest extends BaseTest
{
    protected static array $fixtures = [
        SampleSubmissionsFixture::class,
    ];

    /**
     * Test that a non-logged-in user can not access the prometheus metrics.
     */
    public function testPrometheusNoAccess(): void
    {
        $this->verifyApiResponse('GET', "/metrics/prometheus", 401);
    }

    /**
     * Test that the Prometheus metrics give the basic expected output format.
     */
    public function testPrometheusMetrics(): void
    {
        $expected =<<<EOF
# HELP domjudge_balloons_longest_waitingtime Current longest waiting time for a balloon
# TYPE domjudge_balloons_longest_waitingtime gauge
domjudge_balloons_longest_waitingtime{contest="demo"} 0
# HELP domjudge_balloons_waiting Balloons left todo
# TYPE domjudge_balloons_waiting gauge
domjudge_balloons_waiting{contest="demo"} 0
# HELP domjudge_queuetasks Number of queued tasks for judgehosts
# TYPE domjudge_queuetasks gauge
domjudge_queuetasks 0
# HELP domjudge_submissions_correct Number of correct submissions
# TYPE domjudge_submissions_correct gauge
domjudge_submissions_correct{contest="demo"} 0
# HELP domjudge_submissions_ignored Number of ignored submissions
# TYPE domjudge_submissions_ignored gauge
domjudge_submissions_ignored{contest="demo"} 0
# HELP domjudge_submissions_judging Number of submissions that are actively judged
# TYPE domjudge_submissions_judging gauge
domjudge_submissions_judging{contest="demo"} 0
# HELP domjudge_submissions_perteam Number of teams that have a queued submission
# TYPE domjudge_submissions_perteam gauge
domjudge_submissions_perteam{contest="demo"} 1
# HELP domjudge_submissions_queued Number of queued submissions
# TYPE domjudge_submissions_queued gauge
domjudge_submissions_queued{contest="demo"} 1
# HELP domjudge_submissions_total Total number of all submissions
# TYPE domjudge_submissions_total gauge
domjudge_submissions_total{contest="demo"} 1
# HELP domjudge_submissions_unverified Number of unverified submissions
# TYPE domjudge_submissions_unverified gauge
domjudge_submissions_unverified{contest="demo"} 0
# HELP domjudge_teams Total number of teams
# TYPE domjudge_teams gauge
domjudge_teams{contest="demo"} 1
# HELP domjudge_teams_correct Number of teams that have solved at least one problem
# TYPE domjudge_teams_correct gauge
domjudge_teams_correct{contest="demo"} 0
# HELP domjudge_teams_logged_in Number of teams logged in
# TYPE domjudge_teams_logged_in gauge
domjudge_teams_logged_in{contest="demo"} 0
# HELP domjudge_teams_logged_in_api Number of teams logged in via API
# TYPE domjudge_teams_logged_in_api gauge
domjudge_teams_logged_in_api{contest="demo"} 0
# HELP domjudge_teams_logged_in_ui Number of teams logged in via UI
# TYPE domjudge_teams_logged_in_ui gauge
domjudge_teams_logged_in_ui{contest="demo"} 0
# HELP domjudge_teams_submitted Number of teams that have submitted at least once
# TYPE domjudge_teams_submitted gauge
domjudge_teams_submitted{contest="demo"} 1
# HELP domjudge_teams_users Total number of users in teams
# TYPE domjudge_teams_users gauge
domjudge_teams_users{contest="demo"} 1
# HELP domjudge_teams_users_logged_in Number of users of teams logged in
# TYPE domjudge_teams_users_logged_in gauge
domjudge_teams_users_logged_in{contest="demo"} 0

EOF;

        $response = $this->verifyApiResponse('GET', "/metrics/prometheus", 200, 'admin');
        static::assertEquals($expected, $response);
    }
}
