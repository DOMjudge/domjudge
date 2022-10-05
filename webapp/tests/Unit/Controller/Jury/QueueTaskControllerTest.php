<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Controller\Jury\QueueTaskController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\QueueTask;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMElement;
use Generator;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QueueTaskControllerTest extends BaseTest
{
    private EntityManagerInterface $em;
    private SubmissionService $submissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->submissionService = self::getContainer()->get(SubmissionService::class);
    }

    /**
     * @dataProvider provideNotAllowed
     */
    public function testNotAllowed(string $role): void
    {
        $this->roles = [$role];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury/queuetasks', 403);
    }

    public function provideNotAllowed(): Generator
    {
        yield ['team'];
        yield ['jury'];
    }

    public function testEmptyByDefault(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury/queuetasks', 200);

        $crawler = $this->getCurrentCrawler();
        $tableBody = $crawler->filter('table.data-table.table.table-sm.table-striped tbody');
        self::assertEquals(0, $tableBody->children()->count());
    }

    public function testData(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        // Create some submissions
        $submissions = $this->addSubmissions();

        // Check that the submissions result in a displayed queue task
        $this->verifyPageResponse('GET', '/jury/queuetasks', 200);
        $crawler = $this->getCurrentCrawler();
        $tableBody = $crawler->filter('table.data-table.table.table-sm.table-striped tbody');
        self::assertEquals(4, $tableBody->children()->count());

        $orderMap = [0, 3, 1, 2];

        // Make sure the order is correct: first the submission from team DOMjudge, then the only submission from
        // the example team and then the other two submissions from DOMjudge
        /** @var DOMElement $child */
        foreach ($tableBody->children() as $index => $child) {
            $submission = $submissions[$orderMap[$index]];
            $rowCrawler = new Crawler($child);
            $this->verifyRowForSubmission($rowCrawler, $submission);
        }

        // Also verify the judgetask page
        $this->verifyJudgetaskPage($submission);

        // Now change the priority of one of the second DOMjudge submission. This should reorder the queue tasks
        /** @var QueueTask $queueTask */
        $queueTask = $this->em->createQueryBuilder()
            ->select('qt')
            ->from(QueueTask::class, 'qt')
            ->join(Judging::class, 'j', Join::WITH, 'j.judgingid = qt.jobid')
            ->andWhere('j.submission = :submission')
            ->setParameter('submission', $submissions[1])
            ->getQuery()
            ->getSingleResult();
        $queueTaskId = $queueTask->getQueueTaskid();
        $priority = JudgeTask::PRIORITY_HIGH;
        $this->verifyPageResponse('GET', "/jury/queuetasks/$queueTaskId/change-priority/$priority", 302);

        // Clear entity manager since we have changed the queue task priority and otherwise we would get the old data
        $this->em->clear();

        // Recheck order
        $this->verifyPageResponse('GET', '/jury/queuetasks', 200);
        $crawler = $this->getCurrentCrawler();
        $tableBody = $crawler->filter('table.data-table.table.table-sm.table-striped tbody');
        self::assertEquals(4, $tableBody->children()->count());

        $orderMap = [1, 0, 3, 2];

        // Make sure the order is correct: first the high prio submission from team DOMjudge, then the first submission
        // for DOMjudge, then the only submission from the example team and finally the last submission from DOMjudge
        /** @var DOMElement $child */
        foreach ($tableBody->children() as $index => $child) {
            $submission = $submissions[$orderMap[$index]];
            $rowCrawler = new Crawler($child);
            $this->verifyRowForSubmission($rowCrawler, $submission);
        }
    }

    /**
     * @dataProvider provideLazyDataSource
     */
    public function testLazy(int $dataSource, int $globalLazy, int $problemLazy): void
    {
        $this->setupDatasource($dataSource);
        $contest = $this->em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);
        $hello = $this->em->getRepository(ContestProblem::class)->find(
            [ 'contest' => $contest, 'problem' => $problem ]
        );
        $hello->setLazyEvalResults($problemLazy);
        $config   = self::getContainer()->get(ConfigurationService::class);
        $eventLog = self::getContainer()->get(EventLogService::class);
        $dj       = self::getContainer()->get(DOMJudgeService::class);
        $config->saveChanges(['lazy_eval_results'=>$globalLazy], $eventLog, $dj);

        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        // Create some submissions
        $submissions = $this->addSubmissions();

        // Check that the submissions result in a displayed queue task
        $this->verifyPageResponse('GET', '/jury/queuetasks', 200);
        $crawler = $this->getCurrentCrawler();
        $tableBody = $crawler->filter('table.data-table.table.table-sm.table-striped tbody');
        $expectedNumberQueueItems = 4;
        // In case we shadow we judge all local submissions to keep analyst working.
        if ($dataSource !== DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL) {
            if ($globalLazy === DOMJudgeService::EVAL_DEMAND) {
                if ($problemLazy === DOMJudgeService::EVAL_DEMAND || (int)$problemLazy === (int)DOMJudgeService::EVAL_DEFAULT) {
                    $expectedNumberQueueItems = 0;
                } else {
                    $expectedNumberQueueItems = 1;
                }
            } elseif ($problemLazy == DOMJudgeService::EVAL_DEMAND) {
                $expectedNumberQueueItems = 3;
            }
        }
        self::assertEquals($expectedNumberQueueItems, $tableBody->children()->count());
    }

    /**
     * @return Submission[]
     */
    protected function addSubmissions(): array
    {
        // Add some submissions
        return [
            $this->addSubmission('DOMjudge', 'hello'),
            $this->addSubmission('DOMjudge', 'fltcmp'),
            $this->addSubmission('DOMjudge', 'boolfind'),
            $this->addSubmission('Example teamname', 'fltcmp'),
        ];
    }

    protected function addSubmission(string $team, string $problem): Submission
    {
        // Add a single submission
        $contest = $this->em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $this->em->getRepository(Team::class)->findOneBy(['name' => $team]);
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problem]);
        return $this->submissionService->submitSolution(
            $team, null, $problem, $contest, 'c',
            [new UploadedFile(__FILE__, "foo.c", null, null, true)],
            null, null, null, null, null, null, $msg
        );
    }

    protected function verifyRowForSubmission(Crawler $rowCrawler, Submission $submission): void
    {
        // Find the queue task for this submission by querying on the judging
        /** @var QueueTask $queueTask */
        $queueTask = $this->em->createQueryBuilder()
            ->select('qt')
            ->from(QueueTask::class, 'qt')
            ->join(Judging::class, 'j', Join::WITH, 'j.judgingid = qt.jobid')
            ->andWhere('j.submission = :submission')
            ->setParameter('submission', $submission)
            ->getQuery()
            ->getSingleResult();

        $queueTaskId = $rowCrawler->filter('td:nth-child(1)')->text(null, true);
        $teamName = $rowCrawler->filter('td:nth-child(2)')->text(null, true);
        $jobId = $rowCrawler->filter('td:nth-child(3)')->text(null, true);
        $priority = $rowCrawler->filter('td:nth-child(4)')->text(null, true);
        $teampriority = $rowCrawler->filter('td:nth-child(5)')->text(null, true);
        $starttime = $rowCrawler->filter('td:nth-child(6)')->text(null, true);

        self::assertEquals($queueTask->getQueueTaskid(), $queueTaskId);
        self::assertEquals($submission->getTeam()->getName(), $teamName);
        self::assertEquals($queueTask->getJobId(), $jobId);
        self::assertEquals(QueueTaskController::PRIORITY_MAP[$queueTask->getPriority()], $priority);
        self::assertEquals($queueTask->getTeamPriority(), $teampriority);
        self::assertEquals('not started yet', $starttime);
    }

    protected function verifyJudgetaskPage(Submission $submission): void
    {
        // Find the queue task for this submission by querying on the judging
        /** @var QueueTask $queueTask */
        $queueTask = $this->em->createQueryBuilder()
            ->select('qt')
            ->from(QueueTask::class, 'qt')
            ->join(Judging::class, 'j', Join::WITH, 'j.judgingid = qt.jobid')
            ->andWhere('j.submission = :submission')
            ->setParameter('submission', $submission)
            ->getQuery()
            ->getSingleResult();
        $queueTaskId = $queueTask->getQueueTaskid();
        $judgeTask = $this->em->getRepository(JudgeTask::class)->findOneBy(['jobid' => $submission->getJudgings()->first()->getJudgingid()]);

        $this->verifyPageResponse('GET', "/jury/queuetasks/$queueTaskId/judgetasks", 200);

        $crawler = $this->getCurrentCrawler();
        self::assertSelectorExists('title:contains("Judge tasks for queue task ' . $queueTaskId . '")');
        self::assertSelectorExists('h1:contains("Judge tasks for queue task ' . $queueTaskId . '")');

        $summary = [];
        $summaryTable = $crawler->filter('div.row div.col-lg-4 table');
        $summaryItems = $summaryTable->filter('tr');
        foreach ($summaryItems as $summaryItem) {
            $summaryItemCrawler = new Crawler($summaryItem);
            $title = $summaryItemCrawler->filter('th')->text(null, true);
            $value = $summaryItemCrawler->filter('td')->text(null, true);
            $summary[$title] = $value;
        }
        $expectedSummary = [
            'Submission' => (string)$submission->getSubmitid(),
            'Judging' => (string)$submission->getJudgings()->first()->getJudgingid(),
            'Priority' => QueueTaskController::PRIORITY_MAP[$queueTask->getPriority()],
            'UUID' => $judgeTask->getUuid(),
        ];

        self::assertEquals($expectedSummary, $summary);

        // Now verify the judge task table itself
        $tableBody = $crawler->filter('table.data-table.table.table-sm.table-striped tbody');

        /** @var JudgeTask[] $judgeTasks */
        $judgeTasks = $this->em->createQueryBuilder()
            ->select('jt', 'jh', 'jr')
            ->from(JudgeTask::class, 'jt')
            ->leftJoin('jt.judgehost', 'jh')
            ->innerJoin('jt.judging_runs', 'jr')
            ->addOrderBy('jt.judgetaskid')
            ->andWhere('jt.jobid = :jobid')
            ->setParameter('jobid', $queueTask->getJobId())
            ->getQuery()->getResult();

        foreach ($tableBody->children() as $index => $child) {
            $judgeTask = $judgeTasks[$index];
            $rowCrawler = new Crawler($child);

            $judgeTaskId = $rowCrawler->filter('td:nth-child(1)')->text(null, true);
            $hostname = $rowCrawler->filter('td:nth-child(2)')->text(null, true);
            $valid = $rowCrawler->filter('td:nth-child(3)')->text(null, true);
            $runid = $rowCrawler->filter('td:nth-child(4)')->text(null, true);
            $starttime = $rowCrawler->filter('td:nth-child(5)')->text(null, true);

            self::assertEquals($judgeTask->getJudgetaskid(), $judgeTaskId);
            self::assertEquals('-', $hostname);
            self::assertEquals('yes', $valid);
            self::assertEquals($judgeTask->getFirstJudgingRun()->getRunid(), $runid);
            self::assertEquals('not started yet', $starttime);
        }
    }

    public function provideLazyDataSource(): Generator
    {
        extract($this->getDatasourceLoops());
        foreach ($dataSources as $str_data_source) {
            $dataSource = (int)$str_data_source;
            foreach ([DOMJudgeService::EVAL_DEMAND,
                      DOMJudgeService::EVAL_FULL] as $globalLazy) {
                foreach ([(int)DOMJudgeService::EVAL_DEFAULT,
                          DOMJudgeService::EVAL_DEMAND,
                          DOMJudgeService::EVAL_FULL] as $problemLazy) {
                    yield [$dataSource, $globalLazy, $problemLazy];
                }
            }
        }
    }
}
