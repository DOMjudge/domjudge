<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\Test\RejudgingFirstToSolveFixture;
use App\Entity\Contest;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Rejudging;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Service\RejudgingService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

        // Get the initial scoreboard.
        $scoreboardService->refreshCache($contest);
        $scoreboard = $scoreboardService->getScoreboard($contest, jury: true);

        // The fixture above sets up that $team1 solved the problem, $team2 didn't.
        // So $team1 also solved it first.
        static::assertTrue($scoreboard->solvedFirst($team1, $contestProblem));
        static::assertFalse($scoreboard->solvedFirst($team2, $contestProblem));

        // Now create a rejudging: it will apply a new fake judging for the submission of $team2 that is correct
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

        // Retrieve the scoreboard again.
        // Note that there is no manual scoreboard refresh necessary here as the rejudging service does all
        // necessary updates.
        $scoreboard = $scoreboardService->getScoreboard($contest, jury: true);

        // Now both teams solved the problem, but $team2 solved it first.
        static::assertFalse($scoreboard->solvedFirst($team1, $contestProblem));
        static::assertTrue($scoreboard->solvedFirst($team2, $contestProblem));
    }
    /**
     * A submission can only belong to one rejudging at a time. createRejudging() checks that
     * before claiming it, but the check reads state from before the claim, so another
     * rejudging can take the submission in between. The claim itself is guarded; this asserts
     * that losing it is noticed, because otherwise we would create a judging and judge tasks
     * for a submission somebody else owns and that work could never be applied.
     */
    public function testSubmissionClaimedByAnotherRejudgingIsSkipped(): void
    {
        // Deliberately not logged in: createRejudging() records the current user as the
        // rejudging's start user, and a security token set up outside this entity manager
        // would be rejected as an unknown entity. A rejudging without a start user is valid.
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']);
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);

        $message = null;
        $submission = $container->get(SubmissionService::class)->submitSolution(
            $team, null, $problem, $contest, 'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
        $judging = $submission->getJudgings()->first();

        // Stand in for the other rejudging: claim the submission directly, so the entity the
        // service already holds still says the submission is free. That is precisely the state
        // the real race leaves behind.
        $otherRejudging = new Rejudging();
        $otherRejudging->setStarttime(Utils::now())->setReason('claimed elsewhere');
        $em->persist($otherRejudging);
        $em->flush();
        $em->getConnection()->executeStatement(
            'UPDATE submission SET rejudgingid = :rejudgingid WHERE submitid = :submitid',
            ['rejudgingid' => $otherRejudging->getRejudgingid(), 'submitid' => $submission->getSubmitid()]
        );

        $skipped = [];
        $rejudging = static::getContainer()->get(RejudgingService::class)->createRejudging(
            'losing the race',
            JudgeTask::PRIORITY_DEFAULT,
            [$judging],
            false,
            null,
            0,
            null,
            $skipped
        );

        static::assertCount(1, $skipped, 'the submission must be reported as skipped');
        static::assertNull($rejudging, 'a rejudging that skipped everything is cleaned up');

        // The submission still belongs to the other rejudging, and no judging was created for
        // the one we tried to start.
        $owner = $em->getConnection()->fetchOne(
            'SELECT rejudgingid FROM submission WHERE submitid = ?',
            [$submission->getSubmitid()]
        );
        static::assertEquals($otherRejudging->getRejudgingid(), $owner);
    }
}
