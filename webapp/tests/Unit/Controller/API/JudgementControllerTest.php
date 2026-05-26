<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\AnotherVisibleTeamSubmissionFixture;
use App\DataFixtures\Test\DemoPreFreezeContestFixture;
use App\DataFixtures\Test\DemoPreUnfreezeContestFixture;
use App\DataFixtures\Test\SampleSubmissionsFixture;

class JudgementControllerTest extends BaseTestCase
{
    // Leave $apiEndpoint null so the inherited list/single boilerplate tests skip themselves.
    // The judgements endpoint has its own specific shape and is exercised by the dedicated tests below.
    protected ?string $apiEndpoint = null;

    protected ?string $apiUser = 'admin';

    protected static array $fixtures = [
        SampleSubmissionsFixture::class,
    ];

    public function testSimplifiedJudgementTypeIdMatchesJudgementType(): void
    {
        $contestId = $this->getDemoContestId();

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, $this->apiUser);
        static::assertNotEmpty($response, 'expected at least one judgement in demo data');

        $checkedAtLeastOne = false;
        foreach ($response as $judgement) {
            $type = $judgement['judgement_type_id'] ?? null;
            static::assertArrayHasKey('simplified_judgement_type_id', $judgement);
            $simplified = $judgement['simplified_judgement_type_id'];

            if ($type === null) {
                static::assertNull($simplified, 'simplified should be null when judgement_type_id is null');
                continue;
            }

            $checkedAtLeastOne = true;
            $expected = match ($type) {
                'AC'    => 'AC',
                'CE'    => 'CE',
                default => 'RE',
            };
            static::assertSame($expected, $simplified, "judgement_type_id=$type should map to $expected");
        }
        static::assertTrue($checkedAtLeastOne, 'expected at least one completed judgement to check');
    }

    public function testSimplifiedJudgementTypeIdAbsentInOlderApiVersionStrict(): void
    {
        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $contestId = $this->getDemoContestId();

            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements?strict=1", 200, $this->apiUser);
            static::assertNotEmpty($response, 'expected at least one judgement in demo data');

            foreach ($response as $judgement) {
                static::assertArrayNotHasKey(
                    'simplified_judgement_type_id',
                    $judgement,
                    'simplified_judgement_type_id must not appear in strict pre-2026-01 responses'
                );
            }
        });
    }

    public function testTeamSeesSimplifiedOnlyForOtherTeamsWhenNotFrozen(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);
        $contestId = $this->getDemoContestId();

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, 'demo');
        static::assertNotEmpty($response, 'demo user should see at least their own judgement plus the other visible team');

        $seenOwn = false;
        $seenOther = false;
        foreach ($response as $judgement) {
            $teamId = $this->resolveTeamIdForJudgement($judgement);
            if ($teamId === 'exteam') {
                $seenOwn = true;
                static::assertSame('WA', $judgement['judgement_type_id'], 'own judgement should expose the full verdict');
                static::assertSame('RE', $judgement['simplified_judgement_type_id']);
            } elseif ($teamId === 'othervisibleteam') {
                $seenOther = true;
                static::assertNull($judgement['judgement_type_id'], 'other teams should have judgement_type_id redacted');
                static::assertSame('RE', $judgement['simplified_judgement_type_id']);
            } else {
                static::fail("unexpected team_id $teamId in response");
            }
        }
        static::assertTrue($seenOwn, 'expected to see own team judgement');
        static::assertTrue($seenOther, 'expected to see another visible team judgement');
    }

    public function testTeamSeesOnlyOwnDuringFreeze(): void
    {
        $this->loadFixtures([DemoPreUnfreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);
        $contestId = $this->getDemoContestId();

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, 'demo');

        foreach ($response as $judgement) {
            $teamId = $this->resolveTeamIdForJudgement($judgement);
            static::assertSame('exteam', $teamId, 'demo user must not see other teams during freeze');
            static::assertNotNull($judgement['judgement_type_id'], 'own judgement should expose the full verdict');
        }
    }

    public function testTeamNeverSeesHiddenTeamJudgements(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class]);
        $contestId = $this->getDemoContestId();

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, 'demo');

        // SampleSubmissionsFixture creates a submission from team DOMjudge, which is in the System
        // category (not visible). The demo user must never see it.
        foreach ($response as $judgement) {
            $teamId = $this->resolveTeamIdForJudgement($judgement);
            static::assertNotSame('domjudge', $teamId, 'demo user must not see hidden team DOMjudge judgement');
        }
    }

    /**
     * Look up the team external id for a judgement by its submission_id (an external id of the submission).
     */
    private function resolveTeamIdForJudgement(array $judgement): string
    {
        $contestId = $this->getDemoContestId();
        $submissionId = $judgement['submission_id'];
        $submission = $this->verifyApiJsonResponse('GET', "/contests/$contestId/submissions/$submissionId", 200, 'admin');
        return $submission['team_id'];
    }
}
