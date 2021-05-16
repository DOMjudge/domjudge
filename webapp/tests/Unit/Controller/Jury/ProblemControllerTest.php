<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\Entity\Problem;

class ProblemControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/problems';
    protected static $exampleEntries = ['Hello World', 'default',5,3,2,1];
    protected static $shortTag       = 'problem';
    protected static $deleteEntities = ['name' => ['Hello World']];
    protected static $getIDFunc      = 'getProbid';
    protected static $className      = Problem::class;
    protected static $DOM_elements   = ['h1' => ['Problems'],
                                        'a.btn[title="Import problem"]' => ['admin' => [" Import problem"],'jury'=>[]]];
    protected static $deleteExtra    = ['pageurl'   => '/jury/problems/3',
                                        'deleteurl' => '/jury/problems/attachments/1/delete',
                                        'selector'  => 'interactor',
                                        'fixture'   => AddProblemAttachmentFixture::class];
}
