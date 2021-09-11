<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;

class TeamAffiliationControllerTest extends JuryControllerTest
{
    protected static $baseUrl          = '/jury/affiliations';
    protected static $exampleEntries   = ['UU','Utrecht University',1];
    protected static $shortTag         = 'affiliation';
    protected static $deleteEntities   = ['shortname' => ['UU']];
    protected static $getIDFunc        = 'getAffilid';
    protected static $className        = TeamAffiliation::class;
    protected static $DOM_elements     = ['h1' => ['Affiliations']];
    protected static $identifingEditAttribute = 'shortname';
    protected static $defaultEditEntityName   = 'UU';
    protected static $addForm          = 'team_affiliation[';
    protected static $addEntitiesShown = ['shortname','name'];
    protected static $addEntities      = [['shortname' => 'short',
                                           'name' => 'New Affil',
                                           'country' => 'NLD',
                                           'comments' => 'Lorem ipsum dolor sit amet.'],
                                          ['shortname' => 'cl',
                                           'name' => 'Countryless',
                                           'country' => ''],
                                          ['shortname' => 'com',
                                           'name' => 'No comment',
                                           'comments' => '']];

    public function testCheckAddEntityAdmin(): void
    {
        if (!$this->dataSourceIsLocal()) {
            $this->markTestSkipped('skipping test if data is not local');
        }
        $config = static::$container->get(ConfigurationService::class);
        $showFlags = $config->get('show_flags');
        // Remove setting country when we don't show it
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
