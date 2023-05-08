<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\JudgeTask;
use App\Entity\Language;
use App\Entity\QueueTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class LanguagesControllerTest extends JuryControllerTestCase
{
    protected static string  $identifyingEditAttribute = 'name';
    protected static ?string $defaultEditEntityName    = 'Java';
    protected static string  $baseUrl                  = '/jury/languages';
    protected static array   $exampleEntries           = ['c', 'csharp', 'Haskell', 'Bash shell', "pas, p", 'no', 'yes', 'R', 'r'];
    protected static string  $shortTag                 = 'language';
    protected static array   $deleteEntities           = ['C++','C#','C','Kotlin'];
    protected static string  $deleteEntityIdentifier   = 'name';
    protected static string  $getIDFunc                = 'getLangid';
    protected static string  $className                = Language::class;
    protected static array   $DOM_elements             = ['h1' => ['Enabled languages', 'Disabled languages']];
    protected static ?string $addPlus                  = 'extensions';
    protected static string  $addForm                  = 'language[';
    protected static array   $addEntitiesShown         = ['langid', 'externalid', 'name', 'timefactor'];
    protected static array   $addEntities              = [['langid' => 'simple',
                                                           'name' => 'Simple',
                                                           'externalid' => 'extSimple',
                                                           'requireEntryPoint' => '0',
                                                           'entryPointDescription' => '',
                                                           'allowSubmit' => '1',
                                                           'allowJudge' => '1',
                                                           'timeFactor' => '1',
                                                           'compileExecutable' => 'java_javac',
                                                           'extensions' => ['1' => 'extension'],
                                                           'filterCompilerFiles' => '1'],
                                                          ['langid' => 'ext',
                                                           'externalid' => 'diffext',
                                                           'name' => 'External'],
                                                          ['langid' => 'entry',
                                                           'requireEntryPoint' => '1',
                                                           'entryPointDescription' => 'shell'],
                                                          ['langid' => 'nosub',
                                                           'allowSubmit' => '0'],
                                                          ['langid' => 'nojud',
                                                           'allowJudge' => '0'],
                                                          ['langid' => 'timef1',
                                                           'timeFactor' => '3'],
                                                          ['langid' => 'timef2',
                                                           'timeFactor' => '1.3'],
                                                          ['langid' => 'timef3',
                                                           'timeFactor' => '0.5'],
                                                          ['langid' => 'comp',
                                                           'compileExecutable' => 'swift'],
                                                          ['langid' => 'multex',
                                                           'extensions' => ['0' => 'a', '1' => 'b',
                                                                            '2' => '1',  '3' => ',']],
                                                          ['langid' => 'nofilt',
                                                           'filterCompilerFiles' => '0']];

    public function testUnlockJudgeTasksFormEdit(): void
    {
        // First, check that adding a submission creates a queue task and 4 judge tasks (1 sample, 3 secret cases).
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

        // Now, disable the language.
        $url = "/jury/languages/c/edit";
        $this->verifyPageResponse('GET', $url, 200);

        $crawler = $this->getCurrentCrawler();
        $form = $crawler->filter('form')->form();
        $formData = $form->getValues();
        $formData['language[allowJudge]'] = '0';
        $this->client->submit($form, $formData);

        // Submit again.
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks.
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(4, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again.
        $formData['language[allowJudge]'] = '1';
        $this->client->submit($form, $formData);

        // This should add more queue and judge tasks.
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(8, $judgeTaskQuery->getSingleScalarResult());
    }

    public function testUnlockJudgeTasksToggle(): void
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

        // Now, disable the language.
        $url = "/jury/languages/c/toggle-judge";
        $this->client->request(Request::METHOD_POST, $url, ['allow_judge' => false]);

        // Submit again.
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks.
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(4, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again.
        $this->client->request(Request::METHOD_POST, $url, ['allow_judge' => true]);

        // This should add more queue and judge tasks.
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(8, $judgeTaskQuery->getSingleScalarResult());
    }
}
