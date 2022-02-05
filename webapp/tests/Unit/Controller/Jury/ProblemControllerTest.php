<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\Entity\Problem;

class ProblemControllerTest extends JuryControllerTest
{
    protected static string  $baseUrl                  = '/jury/problems';
    protected static array   $exampleEntries           = ['Hello World', 'default', 5, 3, 2, 1];
    protected static string  $shortTag                 = 'problem';
    protected static array   $deleteEntities           = ['name' => ['Hello World']];
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
                                                 'runExecutable'      => 'boolfind_run',
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
}
