<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleAffiliationsFixture;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;

class TeamAffiliationControllerTest extends JuryControllerTestCase
{
    protected static string  $baseUrl                  = '/jury/affiliations';
    protected static array   $exampleEntries           = ['UU', 'Utrecht University', 1];
    protected static string  $shortTag                 = 'affiliation';
    protected static array   $deleteEntities           = ['UU','FAU'];
    protected static string  $deleteEntityIdentifier   = 'shortname';
    protected static array   $deleteFixtures           = [SampleAffiliationsFixture::class];
    protected static string  $getIDFunc                = 'getAffilid';
    protected static string  $className                = TeamAffiliation::class;
    protected static array   $DOM_elements             = ['h1' => ['Affiliations']];
    protected static string  $identifyingEditAttribute = 'shortname';
    protected static ?string $defaultEditEntityName    = 'UU';
    protected static string  $addForm                  = 'team_affiliation[';
    protected static array   $addEntitiesShown         = ['shortname', 'name'];
    protected static array   $overviewSingleNotShown   = ['country'];
    protected static array   $addEntities              = [['shortname' => 'short',
                                                           'name' => 'New Affil',
                                                           'country' => 'NLD',
                                                           'internalcomments'=> 'Lorem ipsum dolor sit amet.',
                                                           'icpcid' => ''],
                                                          ['shortname' => 'cl',
                                                           'name' => 'Countryless',
                                                           'country' => ''],
                                                          ['shortname' => 'com',
                                                           'name' => 'No comment',
                                                           'internalcomments' => ''],
                                                          ['name' => 'icpc (string)',
                                                           'icpcid' => 'one'],
                                                          ['name' => 'icpc (number)',
                                                           'icpcid' => '15'],
                                                          ['name' => 'icpc (alpnum-_)',
                                                           'icpcid' => '-_1aZ'],
                                                          ['name' => 'Special chars 😀',
                                                           'shortname' => 'yes😀'],
                                                          ['name' => 'External set',
                                                           'externalid' => 'ext12-._']];
    protected static array   $addEntitiesFailure       = ['This value should not be blank.' => [['shortname' => ''],
                                                                                                ['name' => '']],
                                                          'Only letters, numbers, dashes and underscores are allowed.' => [['icpcid' => '()viol'],
                                                                                                                           ['icpcid' => '|viol']],
                                                          'Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => '()']]];

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        $config = static::getContainer()->get(ConfigurationService::class);
        $showFlags = $config->get('show_flags');
        // Remove setting country when we don't show it.
        if (!$showFlags) {
            unset($entity['country']);
            unset($expected['country']);
        }
        return [$entity, $expected];
    }
}
