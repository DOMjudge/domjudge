<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * This abstract class will have the default functionality tested for Jury pages.
 *
 * @package App\Tests\Unit\Controller\Jury
 */
abstract class JuryControllerTest extends BaseTest
{
    protected array $roles                            = ['admin'];
    protected string $addButton                       = '';
    protected string $editButton                      = ' Edit';
    protected string $deleteButton                    = ' Delete';
    protected static array $rolesView                 = ['admin', 'jury'];
    protected static array $rolesDisallowed           = ['team'];
    protected static array $exampleEntries            = ['overwrite_in_class'];
    protected static string $prefixURL                = 'http://localhost';
    protected static string $add                      = '/add';
    protected static string $edit                     = '/edit';
    protected static ?string $editDefault             = '/edit';
    protected static string $delete                   = '/delete';
    protected static string $deleteDefault            = '/delete';
    protected static array $deleteEntities            = [];
    protected static string $identifyingEditAttribute = '';
    protected static string $deleteEntityIdentifier   = '';
    protected static array $deleteFixtures            = [];
    protected static string $shortTag                 = '';
    protected static ?string $addPlus                 = null;
    protected static string $addForm                  = '';
    protected static ?array $deleteExtra              = null;
    protected static array $addEntities               = [];
    protected static array $addEntitiesCount          = [];
    protected static ?string $defaultEditEntityName   = null;
    protected static array $specialFieldOnlyUpdate    = [];
    protected static array $editEntitiesSkipFields    = [];
    protected static array $overviewNotShown          = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->addButton = ' Add new ' . static::$shortTag;
    }

    /**
     * @var String[]|array[] $DOM_elements
     */
    protected static array $DOM_elements;
    protected static string $className;
    protected static string $getIDFunc;

    /**
     * Test that jury <???> overview page exists.
     * @dataProvider provideBasePage
     */
    public function testPageOverview(
        string $role,
        int $statusCode,
        array $elements,
        string $standardEntry
    ): void {
        $this->roles = [$role];
        // Alternative: $this->setupUser();
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, $statusCode);
        if ($statusCode === 200) {
            $crawler = $this->getCurrentCrawler();
            foreach ($elements as $element => $values) {
                $DOM = $crawler->filter($element)->extract(['_text']);
                foreach ($values as $key => $value) {
                    self::assertEquals($value, $DOM[$key]);
                }
            }
            self::assertSelectorExists('body:contains("' . $standardEntry . '")');
        }
    }

    /**
     * @dataProvider provideRoleAccessData
     */
    public function testHTTPAccessForRole(string $role, string $url, int $statusCode, string $HTTPMethod): void
    {
        $this->roles = [$role];
        // Optionally use the setupUser
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse($HTTPMethod, $url, $statusCode);
    }

    /**
     * Data provider used to test role access. Each item contains:
     * - the base role the user has,
     * - the endpoint to check,
     * - expected statusCode for this role,
     * - the method to try (GET, POST).
     */
    public function provideRoleAccessData(): Generator
    {
        foreach (['GET', 'POST', 'HEAD'] as $HTTP) {
            foreach (['admin', 'jury'] as $role) {
                yield [$role, static::$baseUrl, 200, $HTTP];
            }
            foreach (['team', 'jury'] as $role) {
                if (static::$add !== '') {
                    yield [$role, static::$baseUrl . static::$add, 403, $HTTP];
                }
            }
            yield ['team', static::$baseUrl, 403, $HTTP];
            if (static::$add !== '') {
                yield ['admin', static::$baseUrl . static::$add, 200, $HTTP];
            }
        }
    }

    /**
     * Data provider used to test if the starting pages are sane
     * - the base role of the user,
     * - the expected HTTP statusCode,
     * - the pre-existing entry.
     */
    public function provideBasePage(): Generator
    {
        foreach (static::$exampleEntries as $exampleEntry) {
            foreach (static::$rolesView as $role) {
                $elements = static::$DOM_elements;
                foreach ($elements as $element => $values) {
                    if (array_key_exists($role, $values)) {
                        $elements[$element] = $values[$role];
                    }
                }
                yield [$role, 200, $elements, (string)$exampleEntry];
            }
        }
        foreach (static::$rolesDisallowed as $role) {
            yield [$role, 403, [], ''];
        }
    }

    /**
     * Test that jury role can NOT add a new entity for this controller.
     */
    public function testCheckAddEntityJury(): void
    {
        $this->roles = ['jury'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$add !== '') {
            self::assertSelectorNotExists('a:contains(' . $this->addButton . ')');
        }
    }

    /**
     * Test that jury role can NOT edit or delete an entity for this controller.
     */
    public function testCheckEditDeleteEntityJury(): void
    {
        $this->roles = ['jury'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        $this->client->followRedirects(true);
        $crawler = $this->getCurrentCrawler();
        // Check if the edit/delete action keys are visible.
        foreach ([static::$editDefault, static::$deleteDefault, static::$edit, static::$delete] as $identifier) {
            if (empty($identifier)) {
                continue;
            }
            $singlePageLink = null;
            foreach ($crawler->filter('a') as $node) {
                if (strpos($node->nodeValue, $identifier) !== false) {
                    $singlePageLink = $node->getAttribute('href');
                    break;
                }
            }
            self::assertEquals(null, $singlePageLink, 'Found link ending with '.$identifier);
        }
        // Find an ID we can edit/delete.
        foreach (array_merge(
            [static::$deleteEntityIdentifier=>array_slice(static::$deleteEntities, 0, 1)],
            [static::$identifyingEditAttribute=>static::$defaultEditEntityName]) as $identifier => $entityShortName) {
            if ($identifier === '' || $entityShortName === '') {
                continue;
            }
            $em = self::getContainer()->get('doctrine')->getManager();
            $ent = $em->getRepository(static::$className)->findOneBy([$identifier => $entityShortName]);
            $entityUrl = static::$baseUrl . '/' . $ent->{static::$getIDFunc}();
            foreach ([static::$delete=>static::$deleteDefault,
                      static::$edit=>static::$editDefault] as $postfix => $default) {
                if ($default === null) {
                    continue;
                }
                $code = 403;
                if ($postfix === '') {
                    $code = 404;
                }
                $this->verifyPageResponse(
                    'GET',
                    $entityUrl . $default,
                    $code
                );
            }
            // Check that the buttons are not visible, on the page itself.
            $this->verifyPageResponse('GET', $entityUrl, 200);
            foreach ([$this->editButton, $this->deleteButton] as $button) {
                self::assertSelectorNotExists('a:contains("' . $button . '")');
            }
        }
    }

    public function helperCheckExistence(string $id, $value, array $element): void
    {
        if (in_array($id, static::$addEntitiesShown)) {
            $tmpValue = $element[$id];
            if (is_bool($value)) {
                $tmpValue = $value ? 'yes' : 'no';
            }
            self::assertSelectorExists('body:contains("' . $tmpValue . '")');
        }
        if (in_array($id, static::$addEntitiesCount)) {
            self::assertSelectorExists('body:contains("' . count($element[$id]) . '")');
        }
    }

    /**
     * Test that admin can add a new entity for this controller.
     */
    public function testCheckAddEntityAdmin(): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$add !== '') {
            self::assertSelectorExists('a:contains(' . $this->addButton . ')');
            foreach (static::$addEntities as $element) {
                $combinedValues = [];
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
                        $combinedValues[$id] = $field;
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
                $this->client->followRedirect();
                foreach ($combinedValues as $key => $value) {
                    if (!is_array($value) && !in_array($key, static::$overviewNotShown)) {
                        self::assertSelectorExists('body:contains("' . $value . '")');
                    }
                }
            }
            $this->verifyPageResponse('GET', static::$baseUrl, 200);
            foreach (static::$addEntities as $element) {
                foreach ($element as $id => $value) {
                    if (is_array($value)) {
                        if (in_array($id, static::$addEntitiesCount)) {
                            self::assertSelectorExists('body:contains("' . count($value) . '")');
                        } else {
                            foreach ($value as $value2) {
                                if (is_array($value2)) {
                                    $this->helperCheckExistence((string)$id, $value2, $element);
                                }
                            }
                        }
                    } else {
                        $this->helperCheckExistence($id, $value, $element);
                    }
                }
            }
        }
    }

    /**
     * Test that admin can add edit an entity for this controller.
     *
     * @dataProvider provideEditEntities
     */
    public function testCheckEditEntityAdmin(string $identifier, array $formDataKeys, array $formDataValues): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixtures(static::$deleteFixtures);
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$edit !== '') {
            $singlePageLink = null;
            $this->client->followRedirects(true);
            $crawler = $this->getCurrentCrawler();
            foreach ($crawler->filter('a') as $node) {
                if (strpos($node->nodeValue, $identifier) !== false) {
                    $singlePageLink = $node->getAttribute('href');
                }
            }
            $this->verifyPageResponse('GET', $singlePageLink, 200);
            $crawler = $this->getCurrentCrawler();
            foreach ($crawler->filter('a') as $node) {
                if (strpos($node->nodeValue, 'Edit') !== false) {
                    $editLink = $node->getAttribute('href');
                }
            }
            $this->verifyPageResponse('GET', $editLink, 200);
            $crawler = $this->getCurrentCrawler();
            foreach ($formDataKeys as $id => $key) {
                $formFields[static::$addForm . $key . "]"] = $formDataValues[$id];
            }
            $button = $this->client->getCrawler()->selectButton('Save');
            $form = $button->form($formFields, 'POST');
            $this->client->submit($form);
            self::assertNotEquals(500, $this->client->getResponse()->getStatusCode());
            $this->verifyPageResponse('GET', $singlePageLink, 200);
            foreach ($formDataValues as $id => $element) {
                if (in_array($formDataKeys[$id], static::$addEntitiesShown)) {
                    self::assertSelectorExists('body:contains("' . $element . '")');
                }
            }
            // Check that the Edit button is visible on an entity page.
            $this->verifyPageResponse('GET', substr($editLink, 0, strlen($editLink)-strlen(static::$edit)), 200);
            self::assertSelectorExists('a:contains("' . $this->editButton . '")');
        }
    }

    public function provideEditEntities(): Generator
    {
        foreach (static::$addEntities as $row) {
            $formdataKeys = [];
            $formdataValues = [];
            foreach (static::$addEntities[0] as $key => $value) {
                if (!in_array($key, static::$editEntitiesSkipFields)) {
                    $formdataKeys[] = $key;
                    // There are some special fields like passwords which we only update when set.
                    if (in_array($key, static::$specialFieldOnlyUpdate)) {
                        $value = '';
                    }
                    $formdataValues[] = $row[$key] ?? $value;
                }
            }
            yield [static::$defaultEditEntityName, $formdataKeys, $formdataValues];
        }
    }

    /**
     * Test that the standard user can delete an entity.
     *
     * @dataProvider provideDeleteEntity
     */
    public function testDeleteEntity(string $entityShortName): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixtures(static::$deleteFixtures);
        // Find a CID we can delete.
        $em = self::getContainer()->get('doctrine')->getManager();
        $ent = $em->getRepository(static::$className)->findOneBy([static::$deleteEntityIdentifier => $entityShortName]);
        $entityUrl = static::$baseUrl . '/' . $ent->{static::$getIDFunc}();
        // Check that the Delete button is visible on an entity page.
        $this->verifyPageResponse('GET', $entityUrl, 200);
        self::assertSelectorExists('a:contains("' . $this->deleteButton . '")');
        // Follow the route via the overview page.
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        self::assertSelectorExists('i[class*=fa-trash-alt]');
        self::assertSelectorExists('body:contains("' . $entityShortName . '")');
        $this->verifyPageResponse(
            'GET',
            $entityUrl . static::$delete,
            200
        );
        $this->client->submitForm('Delete', []);
        self::assertSelectorNotExists('body:contains("' . $entityShortName . '")');
    }

    /**
     * - entityShortname to delete.
     */
    public function provideDeleteEntity(): Generator
    {
        if (static::$delete !== '') {
            foreach (static::$deleteEntities as $entity) {
                yield [$entity];
            }
        } else {
            self::markTestSkipped("No deletable entity.");
        }
    }

    public function testDeleteExtraEntity(): void
    {
        if (static::$deleteExtra !== null) {
            if (isset(static::$deleteExtra['fixture'])) {
                $this->loadFixture(static::$deleteExtra['fixture']);
            }
            $this->verifyPageResponse('GET', static::$deleteExtra['pageurl'], 200);
            self::assertSelectorExists('body:contains("' . static::$deleteExtra['selector'] . '")');
            $this->verifyPageResponse('GET', static::$deleteExtra['deleteurl'], 200);
            $this->client->submitForm('Delete', []);
            self::assertSelectorNotExists('body:contains("' . static::$deleteExtra['selector'] . '")');
            $this->verifyPageResponse('GET', static::$deleteExtra['deleteurl'], 404);
        } else {
            self::assertTrue(true, "Test skipped");
        }
    }

    protected function addSubmission(string $team, string $problem, string $contest = 'demo'): Submission
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => $contest]);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => $team]);
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => $problem]);
        /** @var SubmissionService $submissionService */
        $submissionService = static::getContainer()->get(SubmissionService::class);
        return $submissionService->submitSolution(
            $team, null, $problem, $contest, 'c',
            [new UploadedFile(__FILE__, "foo.c", null, null, true)]
        );
    }
}
