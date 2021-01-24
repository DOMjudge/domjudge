<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Problem;

class ProblemControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/problems';
    protected static $deleteEntities    = array('name' => ['Hello World']);
    protected static $getIDFunc            = 'getProbid';
    protected static $exampleEntries    = ['Hello World', 'default',5,3,2,1];
    protected static $shortTag          = 'problem';

    protected static $DOM_elements      = array('h1' => ['Problems']);
    protected static $className         = Problem::class;
}
