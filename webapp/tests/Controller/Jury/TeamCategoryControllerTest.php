<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\TeamCategory;

class TeamCategoryControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/categories';
    protected static $deleteEntities    = array('name' => ['System']);
    protected static $getIDFunc            = 'getCategoryid';
    protected static $exampleEntries    = ['Participants','Observers','System','yes','no'];
    protected static $shortTag          = 'category';

    protected static $DOM_elements      = array('h1' => ['Categories']);
    protected static $className         = TeamCategory::class;
}
