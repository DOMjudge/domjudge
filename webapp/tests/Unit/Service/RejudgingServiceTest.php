<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\Test\RejudgingFirstToSolveFixture;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\Team;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;

class RejudgingServiceTest extends BaseTestCase
{
    /**
     * Test that the first to solve is correctly updated when a rejudging is finished
     */
    public function testUpdateFirstToSolve(): void
    {
        $this->logIn();

        /** @var RejudgingService $rejudgingService */
        $rejudgingService = static::getContainer()->get(RejudgingService::class);
        /** @var ScoreboardService $scoreboardService */
        $scoreboardService = static::getContainer()->get(ScoreboardService::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->loadFixture(RejudgingFirstToSolveFixture::class);

        $contest = $entityManager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team1 = $entityManager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $team2 = $entityManager->getRepository(Team::class)->findOneBy(['name' => 'Another team']);
        $problem = $entityManager->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);
        $contestProblem = $problem->getContestProblems()->first();

        // Get the initial scoreboard. team 1 should have the FTS for the problem, team 2 shouldn't
        foreach ([$team1, $team2] as $team) {
            $scoreboardService->calculateScoreRow($contest, $team, $problem);
            $scoreboardService->calculateTeamRank($contest, $team);
        }

        $scoreboard = $scoreboardService->getScoreboard($contest, true);

        static::assertTrue($scoreboard->solvedFirst($team1, $contestProblem));
        static::assertFalse($scoreboard->solvedFirst($team2, $contestProblem));

        // Now create a rejudging: it will apply a new judging for the submission of $team2 that is correct
        $rejudging = (new Rejudging())
            ->setStarttime(Utils::now())
            ->setReason(__METHOD__);
        $submissionToToggle = $team2->getSubmissions()->first();
        $existingJudging = $submissionToToggle->getJudgings()->first();
        $newJudging = (new Judging())
            ->setContest($contest)
            ->setStarttime($existingJudging->getStarttime())
            ->setEndtime($existingJudging->getEndtime())
            ->setRejudging($rejudging)
            ->setValid(false)
            ->setResult('correct');
        $newJudging->setSubmission($submissionToToggle);
        $submissionToToggle->addJudging($newJudging);
        $submissionToToggle->setRejudging($rejudging);
        $entityManager->persist($rejudging);
        $entityManager->persist($newJudging);
        $entityManager->flush();

        // Now apply the rejudging
        $rejudgingService->finishRejudging($rejudging, RejudgingService::ACTION_APPLY);

        // Finally, get the scoreboard again and test if the first to solve changed
        $scoreboard = $scoreboardService->getScoreboard($contest, true);

        static::assertFalse($scoreboard->solvedFirst($team1, $contestProblem));
        static::assertTrue($scoreboard->solvedFirst($team2, $contestProblem));
    }
}
