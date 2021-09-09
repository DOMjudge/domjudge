<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Executable;

class ExecutableControllerTest extends JuryControllerTest
{
    protected static $identifingEditAttribute = 'execid';
    protected static $defaultEditEntityName   = 'adb';
    protected static $baseUrl          = '/jury/executables';
    protected static $exampleEntries   = ['adb','run','boolfind run and compare'];
    protected static $shortTag         = 'executable';
    protected static $deleteEntities   = ['description' => ['adb']];
    protected static $getIDFunc        = 'getExecid';
    protected static $className        = Executable::class;
    protected static $DOM_elements     = ['h1' => ['Executables']];
    protected static $addForm          = 'executable_upload[';
    protected static $addEntitiesShown = ['type'];
    protected static $addEntities      = [];
}
