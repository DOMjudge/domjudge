<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\Test\SampleTeamsFixture;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\ScoreCache;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;

class ScoreboardServiceTest extends BaseTestCase
{
    /**
     * Test that a delayed submission result still results in a correct first to solve
     */
    public function testFirstToSolveDelayed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loadFixture(SampleTeamsFixture::class);
        $team1 = $this->fixtureExecutor->getReferenceRepository()->getReference(SampleTeamsFixture::FIRST_TEAM_REFERENCE);
        $team2 = $this->fixtureExecutor->getReferenceRepository()->getReference(SampleTeamsFixture::SECOND_TEAM_REFERENCE);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contestProblem = $contest->getProblems()->first();
        $problem = $contestProblem->getProblem();

        $scoreboardService = static::getContainer()->get(ScoreboardService::class);

        // Create a submission for both team 1 and team 2.
        // The submission for team 1 will be pending, while the submission
        // for team 2 will be correct.
        $submission1 = $this->createSubmission($em, $team1, $contestProblem, $contest, null);
        $submission2 = $this->createSubmission($em, $team2, $contestProblem, $contest, 'correct');
        $em->flush();

        $scoreboardService->calculateScoreRow($contest, $team1, $problem);
        $scoreboardService->calculateScoreRow($contest, $team2, $problem);

        /** @var ScoreCache $scoreCacheTeam1 */
        $scoreCacheTeam1 = $em->getRepository(ScoreCache::class)->findOneBy([
            'contest' => $contest,
            'team' => $team1,
            'problem' => $problem,
        ]);
        /** @var ScoreCache $scoreCacheTeam2 */
        $scoreCacheTeam2 = $em->getRepository(ScoreCache::class)->findOneBy([
            'contest' => $contest,
            'team' => $team2,
            'problem' => $problem,
        ]);

        static::assertFalse($scoreCacheTeam1->getIsFirstToSolve());
        static::assertFalse($scoreCacheTeam2->getIsFirstToSolve());

        // Now update the submission for team 1 to be wrong
        $submission1->getJudgings()->first()->setResult('wrong');
        $em->flush();

        $scoreboardService->calculateScoreRow($contest, $team1, $problem);

        // We need to clear the entity manager to make sure we get the updated score caches.
        $em->clear();

        /** @var ScoreCache $scoreCacheTeam1 */
        $scoreCacheTeam1 = $em->getRepository(ScoreCache::class)->findOneBy([
            'contest' => $contest,
            'team' => $team1,
            'problem' => $problem,
        ]);
        /** @var ScoreCache $scoreCacheTeam2 */
        $scoreCacheTeam2 = $em->getRepository(ScoreCache::class)->findOneBy([
            'contest' => $contest,
            'team' => $team2,
            'problem' => $problem,
        ]);

        static::assertFalse($scoreCacheTeam1->getIsFirstToSolve());
        static::assertTrue($scoreCacheTeam2->getIsFirstToSolve());
    }

    protected function createSubmission(
        EntityManagerInterface $em,
        Team $team,
        ContestProblem $problem,
        Contest $contest,
        ?string $result
    ): Submission {
        $submission = new Submission();
        $submission
            ->setTeam($team)
            ->setProblem($problem->getProblem())
            ->setContest($contest)
            ->setContestProblem($problem)
            ->setLanguage($em->getRepository(Language::class)->findOneBy(['name' => 'C++']))
            ->setValid(true)
            ->setSubmittime(Utils::now())
            ->addJudging(
                $judging = (new Judging())
                    ->setValid(true)
                    ->setStarttime(Utils::now())
                    ->setEndtime(Utils::now())
                    ->setSubmission($submission)
                    ->setResult($result)
            );

        $em->persist($submission);
        $em->persist($judging);

        return $submission;
    }
}
