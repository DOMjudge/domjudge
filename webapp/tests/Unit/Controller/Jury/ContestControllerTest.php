<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Contest;
use App\Entity\JudgeTask;
use App\Entity\QueueTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class ContestControllerTest extends JuryControllerTestCase
{
    protected static string  $identifyingEditAttribute = 'shortname';
    protected static ?string $defaultEditEntityName    = 'demo';
    protected static string  $baseUrl                  = '/jury/contests';
    protected static array   $exampleEntries           = ['Demo contest'];
    protected static string  $shortTag                 = 'contest';
    protected static array   $deleteEntities           = ['Demo contest'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getCid';
    protected static string  $className                = Contest::class;
    protected static array $DOM_elements               = ['h1'                            => ['Contests'],
                                                          'h3'                            => ['admin' => ['Current contests', 'All available contests'],
                                                             'jury' => []],
                                                          'a.btn[title="Import contest"]' => ['admin' => [" Import contest"],'jury'=>[]]];
    protected static ?array $deleteExtra               = ['pageurl'   => '/jury/contests/1',
                                                          'deleteurl' => '/jury/contests/1/problems/3/delete',
                                                          'selector'  => 'Boolean switch search',
                                                          'fixture'   => null];
    protected static string $addForm                   = 'contest[';
    protected static ?string $addPlus                  = 'problems';
    protected static array $addEntitiesShown           = ['shortname', 'name'];
    protected static array $addEntities                = [['shortname'                => 'nc',
                                                           'name'                     => 'New Contest',
                                                           'activatetimeString'       => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'starttimeString'          => '2021-07-17 16:09:00 Europe/Amsterdam',
                                                           'freezetimeString'         => '2021-07-17 16:10:00 Europe/Amsterdam',
                                                           'endtimeString'            => '2021-07-17 16:11:00 Europe/Amsterdam',
                                                           'unfreezetimeString'       => '2021-07-17 16:12:00 Europe/Amsterdam',
                                                           'deactivatetimeString'     => '2021-07-17 16:13:00 Europe/Amsterdam',
                                                           'processBalloons'          => '1',
                                                           'medalsEnabled'            => '1',
                                                           'enabled'                  => '1',
                                                           'runtimeAsScoreTiebreaker' => '1',
                                                           'openToAllTeams'           => '1',
                                                           'public'                   => '1',
                                                           'starttimeEnabled'         => '1',
                                                           'goldMedals'               => '1',
                                                           'silverMedals'             => '1',
                                                           'bronzeMedals'             => '1',
                                                           'medalCategories'          => ['0' => '2']],
                                                          ['shortname'            => 'CLICS_offset_HMM',
                                                           'name'                 => 'No Timezone but only offset',
                                                           'activatetimeString'   => '2021-07-17 16:08:00+1:11',
                                                           'starttimeString'      => '2021-07-17 16:09:00+1:11',
                                                           'freezetimeString'     => '2021-07-17 16:10:00+1:11',
                                                           'endtimeString'        => '2021-07-17 16:11:00+1:11',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00+1:11',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00+1:11'],
                                                          ['shortname'            => 'CLICS_offset_HHMM',
                                                           'name'                 => 'No Timezone but only offset',
                                                           'activatetimeString'   => '2021-07-17 16:08:00+11:11',
                                                           'starttimeString'      => '2021-07-17 16:09:00+11:11',
                                                           'freezetimeString'     => '2021-07-17 16:10:00+11:11',
                                                           'endtimeString'        => '2021-07-17 16:11:00+11:11',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00+11:11',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00+11:11'],
                                                          ['shortname'            => 'CLICS_offset_0H00',
                                                           'name'                 => 'No Timezone but only offset',
                                                           'activatetimeString'   => '2021-07-17 16:08:00+01:00',
                                                           'starttimeString'      => '2021-07-17 16:09:00+01:00',
                                                           'freezetimeString'     => '2021-07-17 16:10:00+01:00',
                                                           'endtimeString'        => '2021-07-17 16:11:00+01:00',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00+01:00',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00+01:00'],
                                                          ['shortname'            => 'CLICS_offset_H',
                                                           'name'                 => 'No Timezone but only offset',
                                                           'activatetimeString'   => '2021-07-17 16:08:00+1',
                                                           'starttimeString'      => '2021-07-17 16:09:00+1',
                                                           'freezetimeString'     => '2021-07-17 16:10:00+1',
                                                           'endtimeString'        => '2021-07-17 16:11:00+1',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00+1',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00+1'],
                                                          ['shortname'            => 'CLICS_offset_-HHH',
                                                           'name'                 => 'No Timezone but only offset',
                                                           'activatetimeString'   => '2021-07-17 16:08:00-01',
                                                           'starttimeString'      => '2021-07-17 16:09:00-01',
                                                           'freezetimeString'     => '2021-07-17 16:10:00-01',
                                                           'endtimeString'        => '2021-07-17 16:11:00-01',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00-01',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00-01'],
                                                          ['shortname'            => 'utc_Z',
                                                           'name'                 => 'UTC (Z)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00Z',
                                                           'starttimeString'      => '2021-07-17 16:09:00.0Z',
                                                           'freezetimeString'     => '2021-07-17 16:10:00.00Z',
                                                           'endtimeString'        => '2021-07-17 16:11:00.000Z',
                                                           'unfreezetimeString'   => '2021-07-17T16:12:00.1Z',
                                                           'deactivatetimeString' => '2021-07-17T16:13:00.2Z'],
                                                          ['shortname'            => 'otzcet',
                                                           'name'                 => 'Other timezone (CET)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 CET',
                                                           'starttimeString'      => '2021-07-17 16:09:00 CET',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 CET',
                                                           'endtimeString'        => '2021-07-17 16:11:00 CET',
                                                           'unfreezetimeString'   => '2021-07-17 16:12:00 CET',
                                                           'deactivatetimeString' => '2021-07-17 16:13:00 CET'],
                                                          ['shortname'            => 'otzAfricaPorto-Novo',
                                                           'name'                 => 'Other timezone (Africa/Porto-Novo)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 Africa/Porto-Novo',
                                                           'starttimeString'      => '2021-07-17 16:09:00 Africa/Porto-Novo',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 Africa/Porto-Novo',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Africa/Porto-Novo',
                                                           'unfreezetimeString'   => '2021-07-17 16:12:00 Africa/Porto-Novo',
                                                           'deactivatetimeString' => '2021-07-17 16:13:00 Africa/Porto-Novo'],
                                                          ['shortname'            => 'otzunder',
                                                           'name'                 => 'Other timezone (Underscore)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 America/Porto_Velho',
                                                           'starttimeString'      => '2021-07-17 16:09:00 America/Porto_Velho',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 America/Porto_Velho',
                                                           'endtimeString'        => '2021-07-17 16:11:00 America/Porto_Velho',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'otzGMT',
                                                           'name'                 => 'Other timezone (GMT)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 Etc/GMT-3',
                                                           'starttimeString'      => '2021-07-17 16:09:00 Etc/GMT-3',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 Etc/GMT-3',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Etc/GMT-3',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'otzGMT2',
                                                           'name'                 => 'Other timezone (GMT)',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 Etc/GMT+3',
                                                           'starttimeString'      => '2021-07-17 16:09:00 Etc/GMT+3',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 Etc/GMT+3',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Etc/GMT+3',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'otzrel',
                                                           'name'                 => 'Other timezone (Relative)',
                                                           'activatetimeString'   => '-10:00',
                                                           'starttimeString'      => '2021-07-17 16:09:00 Atlantic/Reykjavik',
                                                           'freezetimeString'     => '+0:01',
                                                           'endtimeString'        => '+1111:11',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'other_split_char',
                                                           'name'                 => 'Other CLICS splitchar',
                                                           'activatetimeString'   => '-10:00',
                                                           'starttimeString'      => '2021-07-17T16:09:00 Atlantic/Reykjavik',
                                                           'freezetimeString'     => '+0:01',
                                                           'endtimeString'        => '+1111:11',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'nofr',
                                                           'name'                 => 'No Freeze',
                                                           'freezetimeString'     => '',
                                                           'unfreezetimeString'   => ''],
                                                          ['shortname'            => 'dirstart',
                                                           'name'                 => 'Direct start minimal',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'starttimeString'      => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'freezetimeString'     => '',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Europe/Amsterdam',
                                                           'unfreezetimeString'   => '',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'dirfreeze',
                                                           'name'                 => 'Direct freeze minimal',
                                                           'activatetimeString'   => '2021-07-17 16:07:59 Europe/Amsterdam',
                                                           'starttimeString'      => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'freezetimeString'     => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Europe/Amsterdam',
                                                           'unfreezetimeString'   => '2021-07-17 16:11:00 Europe/Amsterdam',
                                                           'deactivatetimeString' => '2021-07-17 16:11:00 Europe/Amsterdam'],
                                                          ['shortname'            => 'dirfreezerel',
                                                           'name'                 => 'Direct freeze minimal relative',
                                                           'activatetimeString'   => '-0:00',
                                                           'starttimeString'      => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'freezetimeString'     => '+0:00',
                                                           'endtimeString'        => '+10:00',
                                                           'unfreezetimeString'   => '+25:00',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname'            => 'dirfreezerelnoplus',
                                                           'name'                 => 'Direct freeze minimal relative',
                                                           'activatetimeString'   => '-0:00',
                                                           'starttimeString'      => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'freezetimeString'     => '0:00',
                                                           'endtimeString'        => '+10:00',
                                                           'unfreezetimeString'   => '25:00',
                                                           'deactivatetimeString' => ''],
                                                          ['shortname' => 'rel',
                                                           'name' => 'Relative contest',
                                                           'activatetimeString' => '-1:00',
                                                           'starttimeString' => '1990-07-17 16:00:00 America/Noronha',
                                                           'freezetimeString' => '+1:00',
                                                           'endtimeString' => '+1:00:01.13',
                                                           'unfreezetimeString' => '+0005:50:50.123456',
                                                           'deactivatetimeString' => '+9999:50:50.123456'],
                                                          ['shortname' => '.dot.dot.'],
                                                          ['shortname' => '1number1n1'],
                                                          ['shortname' => '_underscore_u_'],
                                                          ['shortname' => '-hyphen-h-'],
                                                          ['shortname' => 'unicode_name',
                                                           'name' => '🕑'],
                                                          ['shortname' => 'printable_ascii.1',
                                                           'name' => '`!#$%&()*+,-./0123456789:;<=>?@A'],
                                                          ['shortname' => 'printable_ascii-2',
                                                           'name' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
                                                          ['shortname' => 'printable_ascii_3',
                                                           'name' => 'abcdefghijklmnopqrstuvwxyz{|}~""'],
                                                          ['shortname' => 'printable_ascii_4',
                                                           'name' => '[]^_`a'],
                                                          ['shortname' => 'printable_ascii_5',
                                                           'name' => "\\''"],
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
                                                          ['shortname' => 'runtimeTie',
                                                           'name' => 'Runtime_as*tie',
                                                           'runtimeAsScoreTiebreaker' => '1'],
                                                          ['shortname' => 'contest_warning',
                                                           'warningMessage' => 'This is not a contest, this is an Unit test'],
                                                          ['shortname' => 'za',
                                                           'name' => 'Zero Medals',
                                                           'goldMedals' => '0',
                                                           'silverMedals' => '0',
                                                           'bronzeMedals' => '0',
                                                           'medalsEnabled' => '1',
                                                           'medalCategories' => ['0' => '2']],
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
                                                                                  'lazyEvalResults' => '1']]],
                                                          ['shortname' => 'singleprobspecialchars',
                                                           'name' => 'Contest shortname with special chars',
                                                           'problems' => ['0' => ['problem' => '2',
                                                                                  'shortname' => '!@#$%^&*()chars<>',
                                                                                  'points' => '2',
                                                                                  'allowSubmit' => '1',
                                                                                  'allowJudge' => '1',
                                                                                  'color' => '#000000',
                                                                                  'lazyEvalResults' => '0']]]];
    protected static array $addEntitiesFailure         = ['Contest should not have multiple timezones.' => [['shortname' => 'tz',
                                                                                                             'name' => 'Timezones',
                                                                                                             'activatetimeString' => '1990-07-17 16:00:00 Africa/Douala',
                                                                                                             'starttimeString' => '1990-07-17 16:00:00 Etc/GMT+2',
                                                                                                             'freezetimeString' => '1990-07-17 16:00:00 America/Paramaribo']],
                                                          'This value should not be blank.' => [['shortname' => '', 'name' => 'Empty'],
                                                                                                ['name' => '', 'shortname' => 'empty_name'],
                                                                                                ['problems' => ['0' => ['problem' => '2',
                                                                                                                        'shortname' => '',
                                                                                                                        'points' => '3',
                                                                                                                        'allowSubmit' => '1',
                                                                                                                        'allowJudge' => '1',
                                                                                                                        'color' => '#000000',
                                                                                                                        'lazyEvalResults' => '0']], 'name' => 'Problem shortname empty']],
                                                          'Unknown problem provided' => [['problems' => ['0' => ['problem' => '',
                                                                                                                 'shortname' => 'empty_problem',
                                                                                                                 'points' => '3',
                                                                                                                 'allowSubmit' => '1',
                                                                                                                 'allowJudge' => '1',
                                                                                                                 'color' => '#000000',
                                                                                                                 'lazyEvalResults' => '0']], 'name' => 'No problem provided']],
                                                          'The selected choice is invalid.' => [['problems' => ['0' => ['problem' => '404',
                                                                                                                        'shortname' => 'unknown_problem',
                                                                                                                        'points' => '3',
                                                                                                                        'allowSubmit' => '1',
                                                                                                                        'allowJudge' => '1',
                                                                                                                        'color' => '#000000',
                                                                                                                        'lazyEvalResults' => '0']], 'name' => 'No known problem']],
                                                          'Only alphanumeric characters and ._- are allowed' => [['shortname' => '"quoted"']]];

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        return [$entity, $expected];
    }

    public function testUnlockJudgeTasks(): void
    {
        // First, check that adding a submission creates a queue task and 4 judge tasks.
        $this->addSubmission('DOMjudge', 'fltcmp');
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $queueTaskQuery = $em->createQueryBuilder()
            ->from(QueueTask::class, 'qt')
            ->select('COUNT(qt)')
            ->getQuery();
        $judgeTaskQuery = $em->createQueryBuilder()
            ->from(JudgeTask::class, 'jt')
            ->select('COUNT(jt)')
            ->getQuery();

        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(4, $judgeTaskQuery->getSingleScalarResult());

        // Now, disable the problem.
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contestId = $contest->getCid();
        $url = "/jury/contests/$contestId/edit";
        $this->verifyPageResponse('GET', $url, 200);

        $crawler = $this->getCurrentCrawler();
        $form = $crawler->filter('form')->form();
        $formData = $form->getValues();
        $problemIndex = null;
        foreach ($formData as $key => $value) {
            if (preg_match('/^contest\[problems\]\[(\d+)\]\[shortname\]$/', $key, $matches) === 1 && $value === 'B') {
                $problemIndex = $matches[1];
                $formData["contest[problems][$problemIndex][allowJudge]"] = '0';
            }
        }

        $this->client->submit($form, $formData);

        // Submit again.
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks.
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(4, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again.
        $formData["contest[problems][$problemIndex][allowJudge]"] = '1';
        $this->client->submit($form, $formData);

        // This should add more queue and judge tasks.
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(8, $judgeTaskQuery->getSingleScalarResult());
    }

    public function testCheckAddMultipleTimezonesAdmin(): void
    {
        $input = ['shortname' => 'tz',
                  'name' => 'Timezones',
                  'activatetimeString' => '1990-07-17 16:00:00 Africa/Douala',
                  'starttimeString' => '1990-07-17 16:00:00 Etc/GMT+2',
                  'freezetimeString' => '1990-07-17 16:00:00 America/Paramaribo'];
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$add !== '') {
            self::assertSelectorExists('a:contains(' . $this->addButton . ')');
            foreach ([$input] as $element) {
                $formFields = [];
                // First fill with default values, the 0th item of the array
                // Overwrite with data to test with.
                foreach ([static::$addEntities[0], $element] as $item) {
                    foreach ($item as $id => $field) {
                        // Skip elements which we cannot set yet.
                        // We can not set checkboxes directly.
                        // We can not set the fields set by JS directly.
                        if (is_bool($field) || $id === static::$addPlus) {
                            continue;
                        }
                        $formId = str_replace('.', '][', $id);
                        $formFields[static::$addForm . $formId . "]"] = $field;
                    }
                }
                $this->verifyPageResponse('GET', static::$baseUrl . static::$add, 200);
                $button = $this->client->getCrawler()->selectButton('Save');
                $form = $button->form($formFields, 'POST');
                $formName = str_replace('[', '', static::$addForm);
                // Get the underlying object to inject elements not currently in the DOM.
                $rawValues = $form->getPhpValues();
                foreach ([static::$addEntities[0], $element] as $item) {
                    if (key_exists(static::$addPlus, $item)) {
                        $rawValues[$formName . static::$addPlus . ']'] = $item[static::$addPlus];
                    }
                }
                // Set checkboxes
                foreach ([static::$addEntities[0], $element] as $item) {
                    foreach ($item as $id => $field) {
                        if (!is_bool($field)) {
                            continue;
                        }
                        if ($field) {
                            $form[$formName][$id]->tick();
                        } else {
                            $form[$formName][$id]->untick();
                        }
                    }
                }
                $this->client->submit($form);
            }
            self::assertSelectorExists('body:contains("Contest should not have multiple timezones.")');
        }
    }

    public function testLockedContest(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contest->setIsLocked(false);
        $contestId = $contest->getCid();
        $editUrl = "/jury/contests/$contestId/edit";
        $deleteUrl = "/jury/contests/$contestId/delete";
        $contestUrl = "/jury/contests/$contestId";
        $em->flush();

        // Contest is unlocked, so we should be able to edit.
        $this->verifyPageResponse('GET', $editUrl, 200);

        // We should see all normal buttons including a lock button.
        $this->verifyPageResponse('GET', $contestUrl, 200);
        $crawler = $this->getCurrentCrawler();
        $titles = $crawler->filterXPath('//div[@class="button-row"]')->children()->each(
            fn(Crawler $node, $i) => $node->attr('title')
        );
        $expectedTitles = [
            'Edit',
            'Delete',
            'Lock',
            'Judge remaining testcases',
            'Heat up judgehosts with contest data',
        ];
        self::assertTrue(array_intersect($titles, $expectedTitles) == $expectedTitles);

        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contest->setIsLocked(true);
        $em->flush();

        // Contest is locked, so we should not be able to edit.
        $this->verifyPageResponse('GET', $editUrl, 302, $contestUrl);

        // We should not see buttons that modify state, but see the normal buttons.
        $this->verifyPageResponse('GET', $contestUrl, 200);
        $crawler = $this->getCurrentCrawler();
        $titles = $crawler->filterXPath('//div[@class="button-row"]')->children()->each(
            fn(Crawler $node, $i) => $node->attr('title')
        );
        $expectedTitles = [
            'Unlock',
            'Judge remaining testcases',
            'Heat up judgehosts with contest data',
        ];
        self::assertTrue(array_intersect($titles, $expectedTitles) == $expectedTitles);
        $unexpectedTitles = [
            'Finalize this contest',
            'Edit',
            'Delete',
            'Lock',
        ];
        self::assertTrue(array_intersect($titles, $unexpectedTitles) == []);

        // Deleting a locked contest does not work.
        $this->verifyPageResponse('GET', $deleteUrl, 302, $contestUrl);

        // Deleting an unlocked contest works.
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contest->setIsLocked(false);
        $em->flush();
        $this->verifyPageResponse('GET', $deleteUrl, 200);
        $crawler = $this->getCurrentCrawler();
        self::assertStringStartsWith('Delete contest ', $crawler->filter('h1')->text());
    }
}
