<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ContestProblem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class ProblemControllerTest extends JuryControllerTestCase
{
    protected static string  $baseUrl                  = '/jury/problems';
    protected static array   $exampleEntries           = ['Hello World', 'default', 5, 3, 2, 1];
    protected static string  $shortTag                 = 'problem';
    protected static array   $deleteEntities           = ['Hello World','Float special compare test'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getProbid';
    protected static string  $className                = Problem::class;
    protected static array   $DOM_elements             = [
        'h1'                            => ['Problems in contest Demo contest'],
        'a.btn[title="Import problem"]' => ['admin' => [" Import problem"], 'jury' => []]
    ];
    protected static string  $identifyingEditAttribute = 'name';
    protected static ?string $defaultEditEntityName    = 'Hello World';
    // Note: we replace the deleteurl in testDeleteExtraEntity below with the actual attachment ID.
    // This can change when running the tests multiple times.
    protected static ?array $deleteExtra      = [
        'pageurl'   => '/jury/problems/3',
        'deleteurl' => '/jury/problems/attachments/1/delete',
        'selector'  => 'interactor'
    ];
    protected static string $addForm          = 'problem[';
    protected static array  $addEntitiesShown = ['name'];
    protected static array  $addEntities      = [['name'                 => 'Problem',
                                                  'timelimit'            => '1',
                                                  'memlimit'             => '1073741824',
                                                  'outputlimit'          => '1073741824',
                                                  'problemstatementFile' => '',
                                                  'runExecutable'        => 'boolfind_cmp',
                                                  'compareExecutable'    => '',
                                                  'specialCompareArgs'   => ''],
                                                 ['name'                 => '🙃 Unicode in name'],
                                                 ['name'                 => 'Long time',
                                                  'timelimit'            => '3600'],
                                                 ['name'                 => 'Default limits',
                                                  'memlimit'             => '',
                                                  'outputlimit'          => ''],
                                                 ['name'                 => 'Args',
                                                  'specialCompareArgs'   => 'args'],
                                                 ['name'                 => 'Args with Unicode',
                                                  'specialCompareArgs'   => '🙃 #Should not happen'],
                                                 ['name'                 => 'Split Run/Compare'],
                                                 ['externalid'           => '._-3xternal1']];
    protected static array  $addEntitiesFailure = ['This value should not be blank.' => [['name' => '']],
                                                   'Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => 'limited_special_chars!']],
                                                   // This is a placeholder on the Add/Edit page
                                                   'leave empty for default' => [['memlimit' => 'zero'],
                                                                                 ['timelimit' => 'zero'],
                                                                                 ['outputlimit' => 'zero'],
                                                                                 ['memlimit' => '-1'],
                                                                                 ['timelimit' => '-1'],
                                                                                 ['outputlimit' => '-1']]];

    public function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        return [$entity, $expected];
    }

    public function testDeleteExtraEntity(): void
    {
        $this->loadFixture(AddProblemAttachmentFixture::class);
        $attachmentId = $this->resolveReference(AddProblemAttachmentFixture::class . ':attachment', ProblemAttachment::class);
        static::$deleteExtra['deleteurl'] = "/jury/problems/attachments/$attachmentId/delete";
        parent::testDeleteExtraEntity();
    }

    public function testLockedContest(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contest->setIsLocked(true);
        $contestId = $contest->getCid();
        $problem = $em->getRepository(Problem::class)->findOneBy(['probid' => 1]);
        $probId = $problem->getProbid();
        $editUrl = "/jury/problems/$probId/edit";
        $deleteUrl = "/jury/problems/$probId/delete";
        $problemUrl = "/jury/problems/$probId";
        $em->flush();

        $this->verifyPageResponse('GET', $problemUrl, 200);

        $crawler = $this->getCurrentCrawler();
        $alertText = $crawler->filterXPath('//div[contains(@class, "alert")]')->first()->text();
        self::assertStringStartsWith('Cannot edit problem, it belongs to locked contest', $alertText);

        $titles = $crawler->filterXPath('//div[@class="button-row"]')->children()->each(
            fn(Crawler $node, $i) => $node->attr('title')
        );
        $expectedTitles = [
            'Judge remaining testcases',
            'Export',
        ];
        self::assertTrue(array_intersect($titles, $expectedTitles) == $expectedTitles);
        $unexpectedTitles = [
            'Edit',
            'Delete',
        ];
        self::assertTrue(array_intersect($titles, $unexpectedTitles) == []);
    }

    public function testMultiDeleteProblems(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Get a contest to associate problems with
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        self::assertNotNull($contest, 'Demo contest not found.');

        // Create some problems to delete
        $problemsData = [
            ['name' => 'Problem 1 for multi-delete', 'externalid' => 'prob1md', 'shortname' => 'MDA'],
            ['name' => 'Problem 2 for multi-delete', 'externalid' => 'prob2md', 'shortname' => 'MDB'],
            ['name' => 'Problem 3 for multi-delete', 'externalid' => 'prob3md', 'shortname' => 'MDC'],
        ];

        $problemIds = [];
        $createdProblems = [];

        foreach ($problemsData as $index => $data) {
            $problem = new Problem();
            $problem->setName($data['name']);
            $problem->setExternalid($data['externalid']);
            $em->persist($problem);

            $contestProblem = new ContestProblem();
            $contestProblem->setProblem($problem);
            $contestProblem->setContest($contest);
            $contestProblem->setShortname($data['shortname']);
            $em->persist($contestProblem);

            $createdProblems[] = $problem;
        }

        $em->flush();

        // Get the IDs of the newly created problems
        foreach ($createdProblems as $problem) {
            $problemIds[] = $problem->getProbid();
        }

        $problem1Id = $problemIds[0];
        $problem2Id = $problemIds[1];
        $problem3Id = $problemIds[2];

        // Verify problems exist before deletion
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        foreach ([1, 2, 3] as $i) {
            self::assertSelectorExists('body:contains("Problem ' . $i . ' for multi-delete")');
        }

        // Simulate multi-delete POST request
        $this->client->request(
            'POST',
            static::getContainer()->get('router')->generate('jury_problem_delete_multiple', ['ids' => [$problem1Id, $problem2Id]]),
            [
                'submit' => 'delete' // Assuming a submit button with name 'submit' and value 'delete'
            ]
        );

        $this->checkStatusAndFollowRedirect();

        // Verify problems are deleted
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorNotExists('body:contains("Problem 1 for multi-delete")');
        self::assertSelectorNotExists('body:contains("Problem 2 for multi-delete")');
        // Problem 3 should still exist
        self::assertSelectorExists('body:contains("Problem 3 for multi-delete")');

        // Verify problem 3 can still be deleted individually
        $this->verifyPageResponse('GET', static::$baseUrl . '/' . $problem3Id . static::$delete, 200);
        $this->client->submitForm('Delete', []);
        $this->checkStatusAndFollowRedirect();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
    }
}
