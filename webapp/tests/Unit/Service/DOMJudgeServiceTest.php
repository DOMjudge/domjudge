<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DOMJudgeServiceTest extends BaseTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function addSubmission(): Submission
    {
        $em = $this->em();

        $message = null;
        return static::getContainer()->get(SubmissionService::class)->submitSolution(
            $em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']),
            null,
            $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']),
            $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']),
            'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
    }

    /**
     * Judge tasks are created for a judging exactly once.
     *
     * unblockJudgeTasks() looks for judgings that have no judge tasks yet, so two callers
     * running at the same time - enabling a language and a problem, say - would both pass that
     * check. Creating a second set would violate the unique key on judging_run and leave judge
     * tasks behind that no judging run points at, which judgehosts then choke on.
     */
    public function testJudgeTasksAreCreatedOnlyOncePerJudging(): void
    {
        $this->logIn();

        $submission = $this->addSubmission();
        $judgingId = $submission->getJudgings()->first()->getJudgingid();

        $em = $this->em();
        $judgeTasksAfterFirst = (int)$em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM judgetask WHERE jobid = ?',
            [$judgingId]
        );
        self::assertGreaterThan(0, $judgeTasksAfterFirst, 'submitting must create judge tasks');

        // A second attempt for the same judging must be a no-op rather than an error.
        $judging = $em->getRepository(Judging::class)->find($judgingId);
        static::getContainer()->get(DOMJudgeService::class)->maybeCreateJudgeTasks($judging);

        self::assertSame(
            $judgeTasksAfterFirst,
            (int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM judgetask WHERE jobid = ?', [$judgingId]),
            'no extra judge tasks may be created'
        );

        // Every judge task still has exactly one judging run, which is what judgehosts rely on.
        $tasks = $em->getRepository(JudgeTask::class)->findBy(['jobid' => $judgingId]);
        foreach ($tasks as $task) {
            $runs = $em->getRepository(JudgingRun::class)->findBy(['judgetaskid' => $task->getJudgetaskid()]);
            self::assertCount(1, $runs, 'each judge task needs exactly one judging run');
        }
    }
}
