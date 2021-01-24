<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Language;

class LanguagesControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/languages';
    protected static $deleteEntities    = array('name' => ['C++']);
    protected static $getIDFunc         = 'getLangid';
    protected static $exampleEntries    = ['c','csharp','Haskell','Bash shell',"pas, p",'no','yes','R','r'];
    protected static $shortTag          = 'language';
    protected static $DOM_elements      = array('h1' => ['Languages']);
    protected static $className         = Language::class;
}
