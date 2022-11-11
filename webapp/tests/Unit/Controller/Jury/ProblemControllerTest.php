<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\Entity\Contest;
use App\Entity\Problem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class ProblemControllerTest extends JuryControllerTest
{
    protected static string  $baseUrl                  = '/jury/problems';
    protected static array   $exampleEntries           = ['Hello World', 'default', 5, 3, 2, 1];
    protected static string  $shortTag                 = 'problem';
    protected static array   $deleteEntities           = ['Hello World','Float special compare test'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getProbid';
    protected static string  $className                = Problem::class;
    protected static array   $DOM_elements             = [
        'h1'                            => ['Problems'],
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
    protected static array  $addEntities      = [['name'               => 'Problem',
                                                 'timelimit'          => '1',
                                                 'memlimit'           => '1073741824',
                                                 'outputlimit'        => '1073741824',
                                                 'problemtextFile'    => '',
                                                 'runExecutable'      => 'boolfind_cmp',
                                                 'compareExecutable'  => '',
                                                 'combinedRunCompare' => true,
                                                 'specialCompareArgs' => ''],
                                                ['name' => 'Long time',
                                                 'timelimit' => '3600'],
                                                ['name' => 'Default limits',
                                                 'memlimit' => '', 'outputlimit' => ''],
                                                ['name' => 'Args',
                                                 'specialCompareArgs' => 'args']];

    public function testCheckAddEntityAdmin(): void
    {
        // Add external IDs when needed.
        if (!$this->dataSourceIsLocal()) {
            foreach (static::$addEntities as &$entity) {
                $entity['externalid'] = md5(json_encode($entity));
            }
            unset($entity);
        }
        parent::testCheckAddEntityAdmin();
    }

    public function testDeleteExtraEntity(): void
    {
        $this->loadFixture(AddProblemAttachmentFixture::class);
        $attachmentId = $this->resolveReference(AddProblemAttachmentFixture::class . ':attachment');
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

        $titles = $crawler->filterXPath('//div[@class="button-row"]')->children()->each(function (Crawler $node, $i) {
            return $node->attr('title');
        });
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
}
