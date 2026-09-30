<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\Executable;

class ExecutableControllerTest extends JuryControllerTestCase
{
    protected static string  $identifyingEditAttribute = 'execid';
    protected static ?string $defaultEditEntityName    = '';
    protected static ?string $editDefault              = null;

    protected static string  $baseUrl                  = '/jury/executables';
    protected static array   $exampleEntries           = ['adb', 'run', 'output validator for Boolean'];
    protected static string  $shortTag                 = 'executable';
    protected static array   $deleteEntities           = ['adb','default run script','rb','default full debug script'];
    protected static string  $deleteEntityIdentifier   = 'description';
    protected static string  $getIDFunc                = 'getExecid';
    protected static string  $className                = Executable::class;
    protected static array   $DOM_elements             = ['h1' => ['Used executables', 'Unused executables']];
    protected static string  $addForm                  = 'executable_upload[';
    protected static array   $addEntitiesShown         = ['type'];
    protected static array   $addEntities              = [];

    /**
     * After changing an executable, the jury is pointed to the submissions that were judged
     * with an outdated version of it, which they can rejudge from the page of the executable.
     */
    public function testChangingExecutableSuggestsRejudging(): void
    {
        $this->addSubmission('DOMjudge', 'hello');

        $this->verifyPageResponse('GET', static::$baseUrl . '/c', 200);
        self::assertSelectorTextContains('#rejudge-modal .modal-title', 'Rejudge outdated judgings for executable c');

        // Saving the files without changing them does not outdate any judging.
        $this->client->submitForm('Save files');
        $this->checkStatusAndFollowRedirect();
        self::assertSelectorNotExists('.alert:contains("outdated version")');

        $form = $this->getCurrentCrawler()->selectButton('Save files')->form();
        $form['form[source0]'] = $form['form[source0]']->getValue() . "# changed\n";
        $this->client->submit($form);
        $this->checkStatusAndFollowRedirect();
        self::assertSelectorExists(
            '.alert-warning:contains("judged with an outdated version of this executable, consider rejudging.")'
        );
    }

    /**
     * Debug scripts are not used for judging, so there is nothing to rejudge.
     */
    public function testNoRejudgingForDebugExecutable(): void
    {
        $this->verifyPageResponse('GET', static::$baseUrl . '/full_debug', 200);
        self::assertSelectorNotExists('#rejudge-modal');
    }
}
