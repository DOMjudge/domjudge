<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\Entity\Problem;

class ProblemControllerTest extends JuryControllerTest
{
    protected static $baseUrl          = '/jury/problems';
    protected static $exampleEntries   = ['Hello World', 'default',5,3,2,1];
    protected static $shortTag         = 'problem';
    protected static $deleteEntities   = ['name' => ['Hello World']];
    protected static $getIDFunc        = 'getProbid';
    protected static $className        = Problem::class;
    protected static $DOM_elements     = ['h1' => ['Problems'],
                                        'a.btn[title="Import problem"]' => ['admin' => [" Import problem"],'jury'=>[]]];
    // Note: we replace the deleteurl in testDeleteExtraEntity below with the actual attachment ID
    // This can change when running the tests multiple times
    protected static $deleteExtra      = ['pageurl'   => '/jury/problems/3',
                                          'deleteurl' => '/jury/problems/attachments/1/delete',
                                          'selector'  => 'interactor'];
    protected static $addForm          = 'problem[';
    protected static $addEntitiesShown = ['name'];
    protected static $addEntities      = [['name' => 'Problem',
                                           'timelimit' => '1',
                                           'memlimit' => '1073741824',
                                           'outputlimit' => '1073741824',
                                           'problemtextFile' => '',
                                           'runExecutable' => 'boolfind_run',
                                           'compareExecutable' => 'boolfind_cmp',
                                           'specialCompareArgs' => ''],
                                          ['name' => 'Long time',
                                           'timelimit' => '3600'],
                                          ['name' => 'Default limits',
                                           'memlimit' => '', 'outputlimit' => ''],
                                          ['name' => 'Args',
                                           'specialCompareArgs' => 'args']];

    public function testDeleteExtraEntity(): void
    {
        $this->loadFixture(AddProblemAttachmentFixture::class);
        $attachmentId = $this->resolveReference(AddProblemAttachmentFixture::class . ':attachment');
        static::$deleteExtra['deleteurl'] = "/jury/problems/attachments/$attachmentId/delete";
        parent::testDeleteExtraEntity();
    }
}
