<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamCategory;

class TeamCategoryControllerTest extends JuryControllerTestCase
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
    protected static array   $addEntities              = [['name'                    => 'New Category',
                                                           'sortorder'               => '1',
                                                           'color'                   => '#123456',
                                                           'visible'                 => '1',
                                                           'allow_self_registration' => '0',
                                                           'icpcid'                  => ''],
                                                          ['name' => 'Secondary',
                                                           'sortorder' => '2'],
                                                          ['name' => 'NonNegative',
                                                           'sortorder' => '0'],
                                                          ['name' => 'Large',
                                                           'sortorder' => '128'],
                                                          ['name' => 'Colorless',
                                                           'color' => ''],
                                                          ['name' => 'FutureColor',
                                                           'color' => 'UnknownColor'],
                                                          ['name' => 'NameColor',
                                                           'color' => 'yellow'],
                                                          ['name' => 'Invisible',
                                                           'visible' => '0'],
                                                          ['name' => 'SelfRegistered',
                                                           'allow_self_registration' => '1'],
                                                          ['name' => 'ICPCid known (string)',
                                                           'icpcid' => 'eleven'],
                                                          ['name' => 'ICPCid known (number)',
                                                           'icpcid' => '11'],
                                                          ['name' => 'ICPCid known (alphanum-_)',
                                                           'icpcid' => '_123ABC-abc'],
                                                          ['name' => 'External set',
                                                           'externalid' => 'ext10-._'],
                                                          ['name' => 'Name with 😁']];
    protected static array   $addEntitiesFailure       = ['Only non-negative sortorders are supported' => [['sortorder' => '-10']],
                                                          'Only letters, numbers, dashes and underscores are allowed.' => [['icpcid' => '|violation', 'name' => 'ICPCid violation-1'],
                                                                                                                           ['icpcid' => '()violation', 'name' => 'ICPCid violation-2']],
                                                          'Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => 'yes|']],
                                                          'This value should not be blank.' => [['name' => '']]];

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        return [$entity, $expected];
    }
}
