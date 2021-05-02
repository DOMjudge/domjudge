<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Language;

class LanguagesControllerTest extends JuryControllerTest
{
    protected static $baseUrl          = '/jury/languages';
    protected static $exampleEntries   = ['c','csharp','Haskell','Bash shell',"pas, p",'no','yes','R','r'];
    protected static $shortTag         = 'language';
    protected static $deleteEntities   = ['name' => ['C++']];
    protected static $getIDFunc        = 'getLangid';
    protected static $className        = Language::class;
    protected static $DOM_elements     = ['h1' => ['Languages']];
    protected static $addForm          = 'language[';
    protected static $addEntitiesShown = ['langid','externalid','name','timefactor'];
    protected static $addEntities      = [];
}
