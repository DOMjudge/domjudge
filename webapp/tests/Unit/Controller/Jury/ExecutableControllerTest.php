<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Executable;

class ExecutableControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'execid';
    protected static ?string $defaultEditEntityName    = 'adb';
    protected static string  $baseUrl                  = '/jury/executables';
    protected static array   $exampleEntries           = ['adb', 'run', 'output validator for boolfind'];
    protected static string  $shortTag                 = 'executable';
    protected static array   $deleteEntities           = ['adb','default run script','rb','default full debug script'];
    protected static string  $deleteEntityIdentifier   = 'description';
    protected static string  $getIDFunc                = 'getExecid';
    protected static string  $className                = Executable::class;
    protected static array   $DOM_elements             = ['h1' => ['Executables']];
    protected static string  $addForm                  = 'executable_upload[';
    protected static array   $addEntitiesShown         = ['type'];
    protected static array   $addEntities              = [];
}
