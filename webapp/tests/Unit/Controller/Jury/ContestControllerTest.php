<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Contest;
use App\Entity\JudgeTask;
use App\Entity\QueueTask;
use Doctrine\ORM\EntityManagerInterface;

class ContestControllerTest extends JuryControllerTest
{
    protected static string  $identifyingEditAttribute = 'shortname';
    protected static ?string $defaultEditEntityName    = 'demoprac';
    protected static string  $baseUrl                  = '/jury/contests';
    protected static array   $exampleEntries           = ['Demo contest', 'Demo practice session'];
    protected static string  $shortTag                 = 'contest';
    protected static array   $deleteEntities           = ['name' => ['Demo practice session']];
    protected static string  $getIDFunc                = 'getCid';
    protected static string  $className                = Contest::class;
    protected static array $DOM_elements               = ['h1'                            => ['Contests'],
                                                          'h3'                            => ['admin' => ['Current contests', 'All available contests'],
                                                             'jury' => []],
                                                          'a.btn[title="Import contest"]' => ['admin' => [" Import contest"],'jury'=>[]]];
    protected static ?array $deleteExtra               = ['pageurl'   => '/jury/contests/2',
                                                          'deleteurl' => '/jury/contests/2/problems/3/delete',
                                                          'selector'  => 'Boolean switch search',
                                                          'fixture'   => null];
    protected static string $addForm                   = 'contest[';
    protected static ?string $addPlus                  = 'problems';
    protected static array $addEntitiesShown           = ['shortname', 'name'];
    protected static array $addEntities                = [['shortname'            => 'nc',
                                                           'name'                 => 'New Contest',
                                                           'activatetimeString'   => '2021-07-17 16:08:00 Europe/Amsterdam',
                                                           'starttimeString'      => '2021-07-17 16:09:00 Europe/Amsterdam',
                                                           'freezetimeString'     => '2021-07-17 16:10:00 Europe/Amsterdam',
                                                           'endtimeString'        => '2021-07-17 16:11:00 Europe/Amsterdam',
                                                           'unfreezetimeString'   => '2021-07-17 16:12:00 Europe/Amsterdam',
                                                           'deactivatetimeString' => '2021-07-17 16:13:00 Europe/Amsterdam',
                                                           'processBalloons'      => '1',
                                                           'medalsEnabled'        => '1',
                                                           'enabled'              => '1',
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
        // Add external ID's when needed.
        if (!$this->dataSourceIsLocal()) {
            foreach (static::$addEntities as &$entity) {
                $entity['externalid'] = $entity['shortname'];
            }
            unset($entity);
        }
        parent::testCheckAddEntityAdmin();
    }

    public function testUnlockJudgeTasks(): void
    {
        // First, check that adding a submission creates a queue task and 3 judge tasks
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
        self::assertEquals(3, $judgeTaskQuery->getSingleScalarResult());

        // Now, disable the problem
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contestId = $contest->getCid();
        $url = "/jury/contests/$contestId/edit";
        $this->verifyPageResponse('GET', $url, 200);

        $crawler = $this->getCurrentCrawler();
        $form = $crawler->filter('form')->form();
        $formData = $form->getValues();
        $problemIndex = null;
        foreach ($formData as $key => $value) {
            if (preg_match('/^contest\[problems\]\[(\d+)\]\[shortname\]$/', $key, $matches) === 1 && $value === 'fltcmp') {
                $problemIndex = $matches[1];
                $formData["contest[problems][$problemIndex][allowJudge]"] = '0';
            }
        }

        $this->client->submit($form, $formData);

        // Submit again
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(3, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again
        $formData["contest[problems][$problemIndex][allowJudge]"] = '1';
        $this->client->submit($form, $formData);

        // This should add more queue and judge tasks
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(6, $judgeTaskQuery->getSingleScalarResult());
    }
}
