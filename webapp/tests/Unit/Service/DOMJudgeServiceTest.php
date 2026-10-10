<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\Contest;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DOMJudgeServiceTest extends BaseTestCase
{
    private const ASSET_PATH = 'test-assets';

    private DOMJudgeService $dj;
    private string $assetDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dj       = static::getContainer()->get(DOMJudgeService::class);
        $this->assetDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/' . self::ASSET_PATH;
        (new Filesystem())->mkdir($this->assetDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->assetDir);
        parent::tearDown();
    }

    public function testGetAssetFilesMatchesOnTheExtensionOnly(): void
    {
        foreach (['ok.css', 'also-ok.css', 'backup.css.bak', 'data.json', 'script.js', 'image.png'] as $file) {
            touch($this->assetDir . '/' . $file);
        }

        self::assertSame(['also-ok.css', 'ok.css'], $this->dj->getAssetFiles(self::ASSET_PATH, ['css']));
        self::assertSame(['image.png', 'script.js'], $this->dj->getAssetFiles(self::ASSET_PATH, ['png', 'js']));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function addSubmission(string $problem = 'hello'): Submission
    {
        $em = $this->em();

        $message = null;
        return static::getContainer()->get(SubmissionService::class)->submitSolution(
            $em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']),
            null,
            $em->getRepository(Problem::class)->findOneBy(['externalid' => $problem]),
            $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']),
            'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
    }

    /**
     * Once an executable changed, the judgings that used an older version of it are
     * outdated, but only for the executables they are judged with.
     */
    public function testJudgingsWithOutdatedExecutable(): void
    {
        $this->logIn();

        // A C submission for a problem that uses the default run and compare scripts.
        $submission = $this->addSubmission();
        $judgingId = $submission->getJudgings()->first()->getJudgingid();
        $executables = [];
        foreach (['c', 'run', 'compare', 'cpp', 'full_debug'] as $execId) {
            $executables[$execId] = $this->em()->getRepository(Executable::class)->find($execId);
        }

        // Debug info is collected with the debug script as run script, but that is not how it was judged.
        $debugTask = (new JudgeTask())
            ->setType(JudgeTaskType::DEBUG_INFO)
            ->setSubmission($submission)
            ->setPriority(JudgeTask::PRIORITY_HIGH)
            ->setJobId($judgingId)
            ->setRunScriptId($executables['full_debug']->getImmutableExecutable()->getImmutableExecId());
        $this->em()->persist($debugTask);
        $this->em()->flush();

        foreach ($executables as $execId => $executable) {
            self::assertNotContains($judgingId, $this->getJudgingsWithOutdatedExecutable($executable),
                "Judged with the current version of $execId.");
        }

        // Storing the same content again does not change anything.
        $this->changeExecutable($executables['compare'], '');
        self::assertNotContains($judgingId, $this->getJudgingsWithOutdatedExecutable($executables['compare']));

        foreach ($executables as $executable) {
            $this->changeExecutable($executable, "\n# changed\n");
        }
        foreach (['c', 'run', 'compare'] as $execId) {
            self::assertContains($judgingId, $this->getJudgingsWithOutdatedExecutable($executables[$execId]),
                "Judged with an older version of $execId.");
        }
        foreach (['cpp', 'full_debug'] as $execId) {
            self::assertNotContains($judgingId, $this->getJudgingsWithOutdatedExecutable($executables[$execId]),
                "Not judged with $execId.");
        }
    }

    /**
     * Interactive problems let their run script compare the output, so changing the
     * default compare script does not affect them.
     */
    public function testJudgingsOfInteractiveProblemWithOutdatedCompareExecutable(): void
    {
        $this->logIn();

        $submission = $this->addSubmission('boolfind');
        self::assertTrue($submission->getProblem()->isInteractiveProblem());
        self::assertNull($submission->getProblem()->getCompareExecutable());
        $judgingId = $submission->getJudgings()->first()->getJudgingid();

        $compare = $this->em()->getRepository(Executable::class)->find('compare');
        $this->changeExecutable($compare, "\n# changed\n");
        self::assertNotContains($judgingId, $this->getJudgingsWithOutdatedExecutable($compare));

        $run = $submission->getProblem()->getRunExecutable();
        $this->changeExecutable($run, "\n# changed\n");
        self::assertContains($judgingId, $this->getJudgingsWithOutdatedExecutable($run));
    }

    /**
     * A problem with its own compare script is not judged with the default one.
     */
    public function testJudgingsOfProblemWithOwnCompareExecutable(): void
    {
        $this->logIn();

        $submission = $this->addSubmission('jumble');
        $ownCompare = $submission->getProblem()->getCompareExecutable();
        self::assertNotNull($ownCompare);
        $judgingId = $submission->getJudgings()->first()->getJudgingid();

        $defaultCompare = $this->em()->getRepository(Executable::class)->find('compare');
        $this->changeExecutable($defaultCompare, "\n# changed\n");
        self::assertNotContains($judgingId, $this->getJudgingsWithOutdatedExecutable($defaultCompare));

        $this->changeExecutable($ownCompare, "\n# changed\n");
        self::assertContains($judgingId, $this->getJudgingsWithOutdatedExecutable($ownCompare));
    }

    /**
     * @return int[]
     */
    private function getJudgingsWithOutdatedExecutable(Executable $executable): array
    {
        $queryBuilder = $this->em()->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->select('j.judgingid');

        return array_map('intval', $this->dj->restrictToJudgingsWithOutdatedExecutable($queryBuilder, $executable)
            ->getQuery()
            ->getSingleColumnResult());
    }

    private function changeExecutable(Executable $executable, string $appendToFiles): void
    {
        $em = $this->em();
        $files = [];
        foreach ($executable->getImmutableExecutable()->getFiles() as $file) {
            $newFile = (new ExecutableFile())
                ->setRank($file->getRank())
                ->setIsExecutable($file->isExecutable())
                ->setFilename($file->getFilename())
                ->setFileContent($file->getFileContent() . $appendToFiles);
            $em->persist($newFile);
            $files[] = $newFile;
        }
        $immutableExecutable = new ImmutableExecutable($files);
        $em->persist($immutableExecutable);
        $executable->setImmutableExecutable($immutableExecutable);
        $em->flush();
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
        $this->dj->maybeCreateJudgeTasks($judging);

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
