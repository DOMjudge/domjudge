<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamCategory;

class TeamCategoryControllerTest extends JuryControllerTest
{
    protected static $baseUrl        = '/jury/categories';
    protected static $exampleEntries = ['Participants','Observers','System','yes','no'];
    protected static $shortTag       = 'category';
    protected static $deleteEntities = ['name' => ['System']];
    protected static $getIDFunc      = 'getCategoryid';
    protected static $className      = TeamCategory::class;
    protected static $DOM_elements   = ['h1' => ['Categories']];
}
