<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\DataFixtures\Test\SampleAffiliationsFixture;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;

class TeamAffiliationControllerTest extends JuryControllerTest
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
    protected static array   $overviewNotShown         = ['country'];
    protected static array   $addEntities              = [['shortname' => 'short',
                                                         'name'      => 'New Affil',
                                                         'country'   => 'NLD',
                                                         'internalcomments'  => 'Lorem ipsum dolor sit amet.'],
                                                        ['shortname' => 'cl',
                                                        'name' => 'Countryless',
                                                        'country' => ''],
                                                       ['shortname' => 'com',
                                                        'name' => 'No comment',
                                                        'internalcomments' => '']];

    public function testCheckAddEntityAdmin(): void
    {
        $config = static::getContainer()->get(ConfigurationService::class);
        $showFlags = $config->get('show_flags');
        // Remove setting country when we don't show it.
        if (!$showFlags) {
            foreach (static::$addEntities as &$entity) {
                unset($entity['country']);
            }
            unset($entity);
        }
        // Add external ID's when needed
        if (!$this->dataSourceIsLocal()) {
            foreach (static::$addEntities as &$entity) {
                $entity['externalid'] = $entity['shortname'];
            }
            unset($entity);
        }
        parent::testCheckAddEntityAdmin();
    }
}
