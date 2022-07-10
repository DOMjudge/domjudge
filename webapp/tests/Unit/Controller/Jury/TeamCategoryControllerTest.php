<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamCategory;

class TeamCategoryControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'name';
    protected static ?string $defaultEditEntityName    = 'System';
    protected static string  $baseUrl                  = '/jury/categories';
    protected static array   $exampleEntries           = ['Participants', 'Observers', 'System', 'yes', 'no'];
    protected static string  $shortTag                 = 'category';
    protected static array   $deleteEntities           = ['System','Observers'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getCategoryid';
    protected static string  $className                = TeamCategory::class;
    protected static array   $DOM_elements             = ['h1' => ['Categories']];
    protected static string  $addForm                  = 'team_category[';
    protected static array   $addEntitiesShown         = ['name', 'sortorder'];
    protected static array  $addEntities               = [['name'                    => 'New Category',
                                                          'sortorder'               => '1',
                                                          'color'                   => '#123456',
                                                          'visible'                 => '1',
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
