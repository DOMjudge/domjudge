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
    protected static string  $getIDFunc                = 'getExternalid';
    protected static string  $className                = Language::class;
    protected static array   $DOM_elements             = ['h1' => ['Enabled languages', 'Disabled languages']];
    protected static ?string $addPlus                  = 'extensions';
    protected static string  $addForm                  = 'language[';
    protected static array   $addEntitiesShown         = ['externalid', 'name', 'timefactor'];
    protected static array   $addEntities              = [['name' => 'Simple',
                                                           'externalid' => 'extSimple',
                                                           'requireEntryPoint' => '0',
                                                           'entryPointDescription' => '',
                                                           'allowSubmit' => '1',
                                                           'allowJudge' => '1',
                                                           'timeFactor' => '1',
                                                           'compileExecutable' => 'java_javac',
                                                           'extensions' => ['1' => 'extension'],
                                                           'filterCompilerFiles' => '1'],
                                                          ['externalid' => 'lang123_.-',
                                                           'name' => 'langid_expected_chars'],
                                                          ['externalid' => 'ext123_.-'],
                                                          ['externalid' => 'name_special_chars',
                                                           'name' => '🕑ড|{}()*'],
                                                          ['externalid' => 'entry',
                                                           'requireEntryPoint' => '1',
                                                           'entryPointDescription' => 'shell'],
                                                          ['externalid' => 'entry_nodesc',
                                                           'requireEntryPoint' => '1',
                                                           'entryPointDescription' => ''],
                                                          ['externalid' => 'nosub',
                                                           'allowSubmit' => '0'],
                                                          ['externalid' => 'nojud',
                                                           'allowJudge' => '0'],
                                                          ['externalid' => 'timef1',
                                                           'timeFactor' => '3'],
                                                          ['externalid' => 'timef2',
                                                           'timeFactor' => '1.3'],
                                                          ['externalid' => 'timef3',
                                                           'timeFactor' => '0.5'],
                                                          ['externalid' => 'comp',
                                                           'compileExecutable' => 'swift'],
                                                          ['externalid' => 'multex',
                                                           'extensions' => ['0' => 'a', '1' => 'b',
                                                                            '2' => '1', '3' => ',']],
                                                           ['externalid' => 'extunicode',
                                                            'extensions' => ['0' => '🕑']],
                                                           ['externalid' => 'nofilt',
                                                            'filterCompilerFiles' => '0'],
                                                           ['externalid' => 'compVers',
                                                            'compilerVersionCommand' => 'unit -V'],
                                                           ['externalid' => 'runVers',
                                                            'runnerVersionCommand' => 'run -x |yes|tr "\n" "\`true\`"']];
    protected static array   $addEntitiesFailure       = ['Only letters, numbers, dashes, underscores and dots are allowed.' => [['externalid' => '§$#'],
                                                                                                                                ['externalid' => '@#()|']],
                                                          'This value should be positive.' => [['timeFactor' => '0'],
                                                                                               ['timeFactor' => '-1'],
                                                                                               ['timeFactor' => '-.1']],
                                                          'This value should not be blank.' => [['name' => '']]];

    public function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        // For LanguageController the values for external identifier should follow internal
        if (key_exists('langid', $entity)) {
            if (!key_exists('externalid', $entity)) {
                $entity['externalid'] = $entity['langid'];
            }
            if (!key_exists('externalid', $expected)) {
                $expected['externalid'] = $entity['langid'];
            }
        }
        return [$entity, $expected];
    }

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
        $this->client->request(Request::METHOD_POST, $url, ['value' => false]);

        // Submit again.
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks.
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(4, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again.
        $this->client->request(Request::METHOD_POST, $url, ['value' => true]);

        // This should add more queue and judge tasks.
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(8, $judgeTaskQuery->getSingleScalarResult());
    }
}
