<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Executable;

class ExecutableControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/executables';
    protected static $shortTag          = 'executable';
    protected static $getIDFunc         = 'getExecid';
    protected static $className         = Executable::class;
    protected static $deleteEntities    = array('description' => ['adb']);
    protected static $exampleEntries    = ['adb','run','boolfind comparator'];
    protected static $DOM_elements      = array('h1' => ['Executables']);
}
