<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamCategory;

class TeamCategoryControllerTest extends JuryControllerTest
{
    protected static $identifingEditAttribute = 'name';
    protected static $defaultEditEntityName   = 'System';
    protected static $baseUrl          = '/jury/categories';
    protected static $exampleEntries   = ['Participants','Observers','System','yes','no'];
    protected static $shortTag         = 'category';
    protected static $deleteEntities   = ['name' => ['System']];
    protected static $getIDFunc        = 'getCategoryid';
    protected static $className        = TeamCategory::class;
    protected static $DOM_elements     = ['h1' => ['Categories']];
    protected static $addForm          = 'team_category[';
    protected static $addEntitiesShown = ['name','sortorder'];
    protected static $addEntities      = [['name' => 'New Category',
                                           'sortorder' => '1',
                                           'color' => '#123456',
                                           'visible' => '1',
                                           'allow_self_registration' => '0'],
                                          ['name' => 'Secondary',
                                           'sortorder' => '2'],
                                          ['name' => 'Colorless',
                                           'color' => ''],
                                          ['name' => 'NameColor',
                                           'color' => 'yellow'],
                                          ['name' => 'Visible',
                                           'visible' => '1'],
                                          ['name' => 'SelfRegistered',
                                           'allow_self_registration' => '1']];
}
