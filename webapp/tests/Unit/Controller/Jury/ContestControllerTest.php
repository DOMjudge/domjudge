<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Contest;

class ContestControllerTest extends JuryControllerTest
{
    protected static $identifingEditAttribute = 'shortname';
    protected static $defaultEditEntityName   = 'demoprac';
    protected static $baseUrl        = '/jury/contests';
    protected static $exampleEntries = ['Demo contest','Demo practice session'];
    protected static $shortTag       = 'contest';
    protected static $deleteEntities = ['name' => ['Demo practice session']];
    protected static $getIDFunc      = 'getCid';
    protected static $className      = Contest::class;
    protected static $DOM_elements   = ['h1' => ['Contests'],
                                        'h3' => ['admin' => ['Current contests', 'All available contests'],
                                                 'jury' => []],
                                        'a.btn[title="Import contest"]' => ['admin' => [" Import contest"],'jury'=>[]]];
    protected static $deleteExtra    = ['pageurl'   => '/jury/contests/2',
                                        'deleteurl' => '/jury/contests/2/problems/3/delete',
                                        'selector'  => 'Boolean switch search',
                                        'fixture'   => null];
    protected static $addForm          = 'contest[';
    protected static $addPlus          = 'problems';
    protected static $addEntitiesShown = ['shortname','name'];
    protected static $addEntities      = [['shortname' => 'nc',
                                           'name' => 'New Contest',
                                           'activatetimeString' => '2021-07-17 16:08:00 Europe/Amsterdam',
                                           'starttimeString' => '2021-07-17 16:09:00 Europe/Amsterdam',
                                           'freezetimeString' => '2021-07-17 16:10:00 Europe/Amsterdam',
                                           'endtimeString' => '2021-07-17 16:11:00 Europe/Amsterdam',
                                           'unfreezetimeString' => '2021-07-17 16:12:00 Europe/Amsterdam',
                                           'deactivatetimeString' => '2021-07-17 16:13:00 Europe/Amsterdam',
                                           'processBalloons' => '1',
                                           'medalsEnabled' => '1',
                                           'enabled' => '1',
                                           'openToAllTeams' => '1',
                                           'public' => '1',
                                           'starttimeEnabled' => '1',
                                           'goldMedals' => '1',
                                           'silverMedals' => '1',
                                           'bronzeMedals' => '1',
                                           'medalCategories' => ['0' => '2']],
                                          ['shortname' => 'rel',
                                           'name' => 'Relative contest',
                                           'activatetimeString' => '-1:00',
                                           'starttimeString' => '1990-07-17 16:00:00 America/Noronha',
                                           'freezetimeString' => '+1:00',
                                           'endtimeString' => '+1:00:01.13',
                                           'unfreezetimeString' => '+0005:50:50.123456',
                                           'deactivatetimeString' => '+9999:50:50.123456'],
                                          ['shortname' => 'na',
                                           'name' => 'No Medals',
                                           'medalsEnabled' => '0',
                                           'medalCategories' => []],
                                          ['shortname' => 'na2',
                                           'name' => 'No Medals 2',
                                           'medalsEnabled' => '0'],
                                          ['shortname' => 'npub',
                                           'name' => 'Not Public',
                                           'public' => '0'],
                                          ['shortname' => 'dst',
                                           'name' => 'Disable startTime',
                                           'starttimeEnabled' => '0'],
                                          ['shortname' => 'nbal',
                                           'name' => 'No balloons',
                                           'processBalloons' => '0'],
                                          ['shortname' => 'dis',
                                           'name' => 'Disabled',
                                           'enabled' => '0'],
                                          ['shortname' => 'nall',
                                           'name' => 'Private contest',
                                           'openToAllTeams' => '0'],
                                          ['shortname' => 'za',
                                           'name' => 'Zero Medals',
                                           'goldMedals' => '0',
                                           'silverMedals' => '0',
                                           'bronzeMedals' => '0',
                                           'medalsEnabled' => '1',
                                           'medalCategories' => ['0' => '2']],
                                          ['shortname' => 'tz',
                                           'name' => 'Timezones',
                                           'activatetimeString' => '1990-07-17 16:00:00 Africa/Douala',
                                           'starttimeString' => '1990-07-17 16:00:00 Etc/GMT+2',
                                           'freezetimeString' => '1990-07-17 16:00:00 America/Paramaribo'],
                                          ['shortname' => 'prob',
                                           'problems' => ['0' => ['shortname' => 'boolfind',
                                                                  'points' => '1',
                                                                  'allowSubmit' => '1',
                                                                  'allowJudge' => '1',
                                                                  'color' => '#ffffff',
                                                                  'lazyEvalResults' => '0',
                                                                  'problem' => '2']]],
                                          ['shortname' => 'multprob',
                                           'name' => 'Contest with problems',
                                           'problems' => ['0' => ['problem' => '2',
                                                                  'shortname' => 'fcmp',
                                                                  'points' => '2',
                                                                  'allowSubmit' => '1',
                                                                  'allowJudge' => '1',
                                                                  'color' => '#000000',
                                                                  'lazyEvalResults' => '0'],
                                                          '1' => ['problem' => '1',
                                                                  'shortname' => 'hw',
                                                                  'points' => '1',
                                                                  'allowSubmit' => '0',
                                                                  'allowJudge' => '1',
                                                                  'color' => '#000000',
                                                                  'lazyEvalResults' => '0'],
                                                          '2' => ['problem' => '3',
                                                                  'shortname' => 'p3',
                                                                  'points' => '1',
                                                                  'allowSubmit' => '1',
                                                                  'allowJudge' => '0',
                                                                  'color' => 'yellow',
                                                                  'lazyEvalResults' => '1']]]];

    public function testCheckAddEntityAdmin(): void
    {
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
