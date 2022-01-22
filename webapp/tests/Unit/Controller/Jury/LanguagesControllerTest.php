<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\JudgeTask;
use App\Entity\Language;
use App\Entity\QueueTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class LanguagesControllerTest extends JuryControllerTest
{
    protected static $identifyingEditAttribute = 'name';
    protected static $defaultEditEntityName    = 'Java';
    protected static $baseUrl          = '/jury/languages';
    protected static $exampleEntries   = ['c','csharp','Haskell','Bash shell',"pas, p",'no','yes','R','r'];
    protected static $shortTag         = 'language';
    protected static $deleteEntities   = ['name' => ['C++']];
    protected static $getIDFunc        = 'getLangid';
    protected static $className        = Language::class;
    protected static $DOM_elements     = ['h1' => ['Languages']];
    protected static $addPlus          = 'extensions';
    protected static $addForm          = 'language[';
    protected static $addEntitiesShown = ['langid','externalid','name','timefactor'];
    protected static $addEntities      = [];

    public function testUnlockJudgeTasksFormEdit(): void
    {
        // First, check that adding a submission creates a queue task and 3 judge tasks
        $this->addSubmission('DOMjudge', 'fltcmp');
        /** @var EntityManagerInterface $em */
        $em = static::$container->get(EntityManagerInterface::class);
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

        // Now, disable the language
        $url = "/jury/languages/c/edit";
        $this->verifyPageResponse('GET', $url, 200);

        $crawler = $this->getCurrentCrawler();
        $form = $crawler->filter('form')->form();
        $formData = $form->getValues();
        $formData['language[allowJudge]'] = '0';
        $this->client->submit($form, $formData);

        // Submit again
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(3, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again
        $formData['language[allowJudge]'] = '1';
        $this->client->submit($form, $formData);

        // This should add more queue and judge tasks
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(6, $judgeTaskQuery->getSingleScalarResult());
    }

    public function testUnlockJudgeTasksToggle(): void
    {
        // First, check that adding a submission creates a queue task and 3 judge tasks
        $this->addSubmission('DOMjudge', 'fltcmp');
        /** @var EntityManagerInterface $em */
        $em = static::$container->get(EntityManagerInterface::class);
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

        // Now, disable the language
        $url = "/jury/languages/c/toggle-judge";
        $this->client->request(Request::METHOD_POST, $url, ['allow_judge' => false]);

        // Submit again
        $this->addSubmission('DOMjudge', 'fltcmp');

        // This should not add more queue or judge tasks
        self::assertEquals(1, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(3, $judgeTaskQuery->getSingleScalarResult());

        // Enable judging again
        $this->client->request(Request::METHOD_POST, $url, ['allow_judge' => true]);

        // This should add more queue and judge tasks
        self::assertEquals(2, $queueTaskQuery->getSingleScalarResult());
        self::assertEquals(6, $judgeTaskQuery->getSingleScalarResult());
    }
}
