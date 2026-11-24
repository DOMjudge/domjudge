<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\DemoNonPublicContestFixture;
use App\DataFixtures\Test\RemoveTeamFromDemoUserFixture;
use App\DataFixtures\Test\SampleEventsFixture;
use App\DataFixtures\Test\SampleSubmissionsThreeTriesCorrectFixture;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ScoreboardControllerTest extends BaseTestCase
{
    protected static array $fixtures = [SampleEventsFixture::class];

    /**
     * Test that the given user has the correct access to the scoreboard for the given contest.
     */
    #[DataProvider('provideScoreboardAccess')]
    public function testScoreboardAccess(
        ?string $user, int $contestId, bool $removeTeamFromDemoUser, bool $expectedAllowedAccess, bool $publicContest
    ): void {
        if ($removeTeamFromDemoUser) {
            $this->loadFixture(RemoveTeamFromDemoUserFixture::class);
        }
        if (!$publicContest) {
            $this->loadFixture(DemoNonPublicContestFixture::class);
        }
        $contestId = $this->resolveEntityId(Contest::class, (string)$contestId);
        $url = "/contests/$contestId/scoreboard";
        $scoreboard = $this->verifyApiJsonResponse('GET', $url, $expectedAllowedAccess ? 200 : 404, $user);
        self::assertNotEmpty($scoreboard);
    }

    public static function provideScoreboardAccess(): Generator
    {
        // Contest 1 is a public contest, everyone should have access.
        yield [null, 1, false, true, true];
        yield ['demo', 1, false, true, true];
        yield ['demo', 1, true, true, true];
        yield ['admin', 1, false, true, true];

        // Contest 1 will be set to non public, not everyone will have access
        yield [null, 1, false, false, false];
        yield ['demo', 1, false, true, false];
        yield ['demo', 1, true, false, false];
        yield ['admin', 1, false, true, false];
    }

    /**
     * Test that filtering works on the demo scoreboard
     */
    #[DataProvider('provideFilters')]
    public function testFilteredScoreboard(array $filters, int $expectedCount): void
    {
        $contestId = $this->resolveEntityId(Contest::class, '1');
        $filter = '?'.implode('&', $filters);
        $url = "/contests/$contestId/scoreboard";
        $scoreboard = $this->verifyApiJsonResponse('GET', $url.$filter, 200, 'admin');
        /** @var array $scoreboardRows */
        $scoreboardRows = $scoreboard['rows'];
        self::assertNotEmpty($scoreboard);
        self::assertCount($expectedCount, $scoreboardRows);
    }

    public function testSolvedInFirstMinuteHasScoreTime(): void
    {
        $this->loadFixture(SampleSubmissionsThreeTriesCorrectFixture::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $submissionId = $this->resolveReference(SampleSubmissionsThreeTriesCorrectFixture::class . ':2', Submission::class);
        /** @var Submission $submission */
        $submission = $em->getRepository(Submission::class)->find($submissionId);
        $contest = $submission->getContest();
        $submission->setSubmittime((float)$contest->getStarttime() + 10);
        $em->flush();
        static::getContainer()->get(ScoreboardService::class)->refreshCache($contest);

        $contestId = $this->resolveEntityId(Contest::class, (string)$contest->getCid());
        $scoreboard = $this->verifyApiJsonResponse('GET', "/contests/$contestId/scoreboard", 200, 'admin');
        $row = array_values(array_filter($scoreboard['rows'], fn(array $row) => $row['team_id'] === 'exteam'))[0];
        $solved = array_values(array_filter($row['problems'], fn(array $problem) => $problem['solved']));

        self::assertSame(1, $row['score']['num_solved']);
        self::assertSame('0:00:00', $row['score']['time']);
        self::assertCount(1, $solved);
        self::assertSame('0:00:00', $solved[0]['time']);
    }

    public function testFilteredScoreboardMedalsUseUnfilteredScoreboard(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        /** @var TeamCategory $participants */
        $participants = $em->getRepository(TeamCategory::class)->findOneBy(['externalid' => 'participants']);
        /** @var Team $firstTeam */
        $firstTeam = $em->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);

        $contest
            ->setMedalsEnabled(true)
            ->setGoldMedals(1)
            ->setSilverMedals(0)
            ->setBronzeMedals(0)
            ->addMedalCategory($participants);

        $secondAffiliation = (new TeamAffiliation())
            ->setExternalid('secondaffil')
            ->setShortname('SA')
            ->setName('Second Affiliation')
            ->setCountry('DEU');
        $secondTeam = (new Team())
            ->setExternalid('secondteam')
            ->setName('Second Team')
            ->addCategory($participants)
            ->setAffiliation($secondAffiliation);
        $em->persist($secondAffiliation);
        $em->persist($secondTeam);

        /** @var ContestProblem $problem */
        $problem = $contest->getProblems()->first();
        /** @var Language $language */
        $language = $em->getRepository(Language::class)->findByExternalId('c');
        $baseTime = (float)$contest->getStarttime();

        foreach ([[$firstTeam, $baseTime + 60], [$secondTeam, $baseTime + 120]] as [$team, $submitTime]) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($team)
                ->setContestProblem($problem)
                ->setLanguage($language)
                ->setSubmittime($submitTime);
            $judging = (new Judging())
                ->setContest($contest)
                ->setStarttime($submitTime)
                ->setEndtime($submitTime + 5)
                ->setValid(true)
                ->setSubmission($submission)
                ->setResult('correct');
            $submission->addJudging($judging);
            $em->persist($submission);
            $em->persist($judging);
        }
        $em->flush();

        static::getContainer()->get(ScoreboardService::class)->refreshCache($contest);

        $contestId = $this->resolveEntityId(Contest::class, (string)$contest->getCid());

        // Filtering to the second team must not award it a gold medal just because it ranks first in the filtered view.
        $filtered = $this->verifyApiJsonResponse('GET', "/contests/$contestId/scoreboard?affiliation=secondaffil", 200, 'admin');
        self::assertCount(1, $filtered['rows']);
        self::assertSame('secondteam', $filtered['rows'][0]['team_id']);
        self::assertArrayNotHasKey('medal', $filtered['rows'][0]);

        // Filtering to the first team still awards it the gold medal from the full scoreboard.
        $filteredFirst = $this->verifyApiJsonResponse('GET', "/contests/$contestId/scoreboard?affiliation=utrecht", 200, 'admin');
        self::assertCount(1, $filteredFirst['rows']);
        self::assertSame('exteam', $filteredFirst['rows'][0]['team_id']);
        self::assertSame('gold-medal', $filteredFirst['rows'][0]['medal']);
    }

    public static function provideFilters(): Generator
    {
        yield [['category=participants'], 1];
        yield [['category=system', 'sortorder=9', 'allteams=true'], 1];
        yield [['category=system', 'sortorder=9'], 0];
        yield [['affiliation=utrecht'], 1];
        yield [['public=true'], 1]; // Scoreboard is frozen but has no results so those are the same
        yield [['public=false'], 1];
        yield [['sortorder=0'], 1];
        yield [['sortorder=9','allteams=false'], 0];
        yield [['sortorder=9','allteams=true'], 1];
        yield [['sortorder=1','allteams=true'], 0];
        yield [['sortorder=999'], 0];
        yield [['allteams=false'], 1];
        yield [['allteams=true'], 1]; // Sortorder has default 0 -> hidden category system has 9
        yield [['country=NLD'], 1];
        yield [['country=AAA'], 0];
        yield [['country=USA'], 0];
        yield [['category=self-registered'], 0];
    }
}
