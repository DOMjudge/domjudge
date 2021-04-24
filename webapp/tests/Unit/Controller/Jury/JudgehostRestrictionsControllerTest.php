<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\JudgehostRestriction;

class JudgehostRestrictionsControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/judgehost-restrictions';
    protected static $exampleEntries = ['No'];
    protected static $shortTag       = 'judgehost restriction';
    protected static $deleteEntities = ['description' => ['adb']];
    protected static $getIDFunc      = 'getRestrictionid';
    protected static $className      = JudgehostRestriction::class;
    protected static $DOM_elements   = ['h1' => ['Judgehost restrictions']];
    protected static $delete         = ''; //TODO: When insert works this can be reset.
}
