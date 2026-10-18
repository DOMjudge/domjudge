<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\AnotherVisibleTeamSubmissionFixture;
use App\DataFixtures\Test\DemoPreFreezeContestFixture;
use App\DataFixtures\Test\DemoPreUnfreezeContestFixture;
use App\DataFixtures\Test\RemoveTeamFromDemoUserFixture;
use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectFixture;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;

class JudgementControllerTest extends BaseTestCase
{
    // Leave $apiEndpoint null so the inherited list/single boilerplate tests skip themselves.
    protected ?string $apiEndpoint = null;

    protected ?string $apiUser = 'admin';

    // Its judgements are TLE, WA and AC, so they cover both RE and AC as simplified type.
    protected static array $fixtures = [
        SampleSubmissionsThreeTriesCorrectFixture::class,
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

    public function testSimplifiedJudgementTypeIdAbsentInOlderApiVersion(): void
    {
        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $contestId = $this->getDemoContestId();

            foreach (['', '?strict=1'] as $query) {
                $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements$query", 200, $this->apiUser);
                static::assertNotEmpty($response, 'expected at least one judgement in demo data');

                foreach ($response as $judgement) {
                    static::assertNull(
                        $judgement['simplified_judgement_type_id'] ?? null,
                        'there is no RE judgement type to refer to before 2026-01'
                    );
                }
            }
        });
    }

    public function testTeamSeesSimplifiedOnlyForOtherTeamsWhenNotFrozen(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);

        $judgements = $this->getJudgementsPerTeam('demo');
        static::assertNotEmpty($judgements['exteam'] ?? []);
        foreach ($judgements['exteam'] as $judgement) {
            static::assertNotNull($judgement['judgement_type_id'], 'own judgements should expose the full verdict');
        }
        static::assertCount(1, $judgements['othervisibleteam'] ?? []);
        static::assertNull($judgements['othervisibleteam'][0]['judgement_type_id']);
        static::assertSame('RE', $judgements['othervisibleteam'][0]['simplified_judgement_type_id']);
    }

    public function testTeamSeesOnlyOwnDuringFreeze(): void
    {
        $this->loadFixtures([DemoPreUnfreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);

        $judgements = $this->getJudgementsPerTeam('demo');
        static::assertSame(['exteam'], array_keys($judgements));
    }

    public function testTeamSeesOnlyOwnBefore2026(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);

        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $judgements = $this->getJudgementsPerTeam('demo');
            static::assertSame(['exteam'], array_keys($judgements));
        });
    }

    public function testTeamSeesOnlyOwnWithoutRanking(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);

        $this->withChangedConfiguration('enable_ranking', false, function (): void {
            $judgements = $this->getJudgementsPerTeam('demo');
            static::assertSame(['exteam'], array_keys($judgements));
        });
    }

    public function testResultFilterDoesNotRevealOtherTeams(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class, AnotherVisibleTeamSubmissionFixture::class]);

        $contestId = $this->getDemoContestId();
        $submissions = $this->verifyApiJsonResponse('GET', "/contests/$contestId/submissions", 200, 'admin');
        $teamPerSubmission = array_column($submissions, 'team_id', 'id');
        $judgements = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, 'admin');
        $otherTeamResults = [];
        foreach ($judgements as $judgement) {
            if ($teamPerSubmission[$judgement['submission_id']] === 'othervisibleteam') {
                $otherTeamResults[] = $judgement['judgement_type_id'];
            }
        }
        static::assertNotEmpty($otherTeamResults);

        foreach ($this->verifyApiJsonResponse(
            'GET', "/contests/$contestId/judgements?result=" . $otherTeamResults[0], 200, 'demo'
        ) as $judgement) {
            static::assertSame('exteam', $teamPerSubmission[$judgement['submission_id']]);
        }
    }

    public function testTeamNeverSeesHiddenTeamJudgements(): void
    {
        $this->loadFixtures([DemoPreFreezeContestFixture::class]);
        // Team DOMjudge is in the hidden System category.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $submittime = Utils::toEpochFloat('2021-02-15 10:00:00');
        $submission = (new Submission())
            ->setContest($contest)
            ->setTeam($em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']))
            ->setContestProblem($contest->getProblems()->first())
            ->setLanguage($em->getRepository(Language::class)->findByExternalId('cpp'))
            ->setSubmittime($submittime);
        $judging = (new Judging())
            ->setContest($contest)
            ->setSubmission($submission)
            ->setStarttime($submittime)
            ->setEndtime($submittime + 5)
            ->setValid(true)
            ->setResult('wrong-answer');
        $submission->addJudging($judging);
        $em->persist($submission);
        $em->persist($judging);
        $em->flush();

        static::assertArrayHasKey('domjudge', $this->getJudgementsPerTeam('admin'));
        static::assertArrayNotHasKey('domjudge', $this->getJudgementsPerTeam('demo'));
    }

    public function testTeamUserWithoutTeamSeesNoDetailedVerdicts(): void
    {
        $this->loadFixtures([
            DemoPreFreezeContestFixture::class,
            AnotherVisibleTeamSubmissionFixture::class,
            RemoveTeamFromDemoUserFixture::class,
        ]);

        $judgementsPerTeam = $this->getJudgementsPerTeam('demo');
        static::assertNotEmpty($judgementsPerTeam);
        foreach ($judgementsPerTeam as $judgements) {
            foreach ($judgements as $judgement) {
                static::assertNull($judgement['judgement_type_id']);
            }
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>> Judgements visible to the user, per team ID
     */
    private function getJudgementsPerTeam(string $user): array
    {
        $contestId = $this->getDemoContestId();
        $submissions = $this->verifyApiJsonResponse('GET', "/contests/$contestId/submissions", 200, 'admin');
        $teamPerSubmission = array_column($submissions, 'team_id', 'id');

        $judgementsPerTeam = [];
        foreach ($this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, $user) as $judgement) {
            $judgementsPerTeam[$teamPerSubmission[$judgement['submission_id']]][] = $judgement;
        }
        return $judgementsPerTeam;
    }
}
