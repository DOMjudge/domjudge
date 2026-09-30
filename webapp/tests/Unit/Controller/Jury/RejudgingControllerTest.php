<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\RejudgingStatesFixture;
use App\Entity\Contest;
use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Entity\ImmutableExecutable;
use App\Entity\Problem;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RejudgingControllerTest extends BaseTestCase
{
    protected array $roles = ['admin'];

    #[DataProvider('provideRoles')]
    public function testStartPage(array $roles, int $http): void
    {
        $this->roles = $roles;
        $this->logOut();
        if ($roles) {
            $this->logIn();
        }
        $this->verifyPageResponse('GET', '/jury/rejudgings', $http);
        if ($http===200) {
            foreach (['No rejudgings defined',' Add new rejudging','Rejudgings'] as $element) {
                self::assertSelectorExists('body:contains("'.$element.'")');
            }
        }
    }

    /**
     * Provide the HTTP access code for the DOMjudge role
     */
    public static function provideRoles(): Generator
    {
        yield [[],302];
        foreach (['team','balloon','clarification_rw'] as $role) {
            yield [[$role],403];
        }
        foreach (['jury','admin'] as $role) {
            yield [[$role],200];
        }
    }

    public function testCorrectSorting(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        // The sorting is done in JS (and cannot be tested), this is the inverse ordering of the Fixture.
        foreach (['Canceled','Finished','0Percent_2','0Percent_1','Unit'] as $index => $reason) {
            self::assertSelectorExists('tr:nth-child('.($index+1).'):contains("'.$reason.'")');
        }
    }

    public function setRejudgingState(?string $contestName): void
    {
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        if ($contestName === null) {
            $cid = -1;
        } else {
            /** @var Contest $contest */
            $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => $contestName]);
            $contest = $contest->setDeactivatetimeString("'2099-01-02 07:07:07'");
            $cid = $contest->getCid();
        }
        $this->loadFixture(RejudgingStatesFixture::class);
        $this->client->request('GET', '/team/change-contest/' . $cid);
    }

    #[DataProvider('provideShownRejudgings')]
    public function testRejudgingCurrentContest(
        ?string $contestName,
        array $shown,
        array $hidden,
        int $todo
    ): void {
        $this->setRejudgingState($contestName);
        $this->verifyPageResponse('GET', '/jury/rejudgings', 200);
        foreach ($shown as $rejudging) {
            self::assertSelectorExists('body:contains("' . $rejudging . '")');
        }
        foreach ($hidden as $rejudging) {
            self::assertSelectorNotExists('body:contains("' . $rejudging . '")');
        }
    }

    #[DataProvider('provideShownRejudgings')]
    public function testRejudgingCounterMenu(
        ?string $contestName,
        array $shown,
        array $hidden,
        int $todo
    ): void {
        $DOMselector = '#menu_rejudgings';
        $this->setRejudgingState($contestName);
        $this->verifyPageResponse('GET', '/jury', 200);
        self::assertSelectorTextContains($DOMselector, 'rejudgings');
        // We cannot count the amount of rejudgings here.

        // We check the page where the data comes from.
        $this->client->request('GET', '/jury/updates');
        $response = $this->client->getResponse();
        $jsonResponse = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        /** @var array $rejudgings */
        $rejudgings = $jsonResponse['rejudgings'];
        static::assertCount($todo, $rejudgings);
    }

    public static function provideShownRejudgings(): Generator
    {
        // The case where no contest is active/chosen/current.
        $show = [];
        foreach (RejudgingStatesFixture::rejudgingStages() as $stage) {
            $show[] = $stage[0];
        }
        yield [null, $show, [], 3];

        // Rejudging during a contest
        $contestName = 'demo';
        $show = [];
        $hidden = [];
        $todo = 0;
        foreach (RejudgingStatesFixture::rejudgingStages() as $stage) {
            if (in_array($contestName, $stage[4])) {
                $show[] = $stage[0];
                if ($stage[1] === null) {
                    $todo++;
                }
            } else {
                $hidden[] = $stage[0];
            }
        }
        yield [$contestName, $show, $hidden, $todo];
    }

    /**
     * Rejudging an executable rejudges the submissions judged with an outdated version of it.
     */
    public function testRejudgeOutdatedJudgingsOfExecutable(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $message = null;
        $submission = static::getContainer()->get(SubmissionService::class)->submitSolution(
            $em->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']),
            null,
            $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']),
            $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']),
            'c',
            [new UploadedFile(__FILE__, 'foo.c', null, null, true)],
            SubmissionSource::UNKNOWN, null, null, null, null, null, $message
        );
        $submitId = $submission->getSubmitid();
        // Pending judgings are not rejudged, so pretend this one is done.
        $submission->getJudgings()->first()->setResult('wrong-answer');
        $em->flush();

        // The submission was judged with the current version of the compile script of C.
        self::assertStringNotContainsString('/jury/rejudgings/', $this->createRejudging('executable', 'c'));
        self::assertNull($this->getRejudgingOfSubmission($submitId));

        $compileExecutable = $em->getRepository(Executable::class)->find('c');
        $files = [];
        foreach ($compileExecutable->getImmutableExecutable()->getFiles() as $file) {
            $newFile = (new ExecutableFile())
                ->setRank($file->getRank())
                ->setIsExecutable($file->isExecutable())
                ->setFilename($file->getFilename())
                ->setFileContent($file->getFileContent() . "# changed\n");
            $em->persist($newFile);
            $files[] = $newFile;
        }
        $immutableExecutable = new ImmutableExecutable($files);
        $em->persist($immutableExecutable);
        $compileExecutable->setImmutableExecutable($immutableExecutable);
        $em->flush();

        $output = $this->createRejudging('executable', 'c');
        self::assertMatchesRegularExpression('#"redirect":"/jury/rejudgings/\d+"#', $output);
        preg_match('#"redirect":"/jury/rejudgings/(\d+)"#', $output, $matches);

        $rejudgingId = $this->getRejudgingOfSubmission($submitId);
        self::assertEquals($matches[1], $rejudgingId);
        self::assertEquals('executable: c', static::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->fetchOne('SELECT reason FROM rejudging WHERE rejudgingid = ?', [$rejudgingId]));
    }

    public function testRejudgeUnknownExecutable(): void
    {
        $this->client->request('POST', '/jury/rejudgings/create', ['table' => 'executable', 'id' => 'nonexistent']);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Create a rejudging like the rejudge button does and return the progress it reported.
     */
    private function createRejudging(string $table, string $id): string
    {
        $this->client->xmlHttpRequest('POST', '/jury/rejudgings/create', ['table' => $table, 'id' => $id]);
        // The progress is streamed, so only the response as received by the browser contains it.
        return $this->client->getInternalResponse()->getContent();
    }

    private function getRejudgingOfSubmission(int $submitId): ?int
    {
        // Rejudgings claim their submissions with a direct query, so do not trust loaded entities.
        $rejudgingId = static::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->fetchOne('SELECT rejudgingid FROM submission WHERE submitid = ?', [$submitId]);
        return $rejudgingId === null ? null : (int)$rejudgingId;
    }
}
