<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\SubmissionService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use DOMElement;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DomCrawler\Crawler;

/**
 * This abstract class will have the default functionality tested for Jury pages.
 *
 * @package App\Tests\Unit\Controller\Jury
 */
abstract class JuryControllerTestCase extends BaseTestCase
{
    protected static string $baseUrl                  = '';
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
    protected static array $addEntitiesShown          = [];
    protected static array $addEntitiesFailure        = [];
    protected static ?string $defaultEditEntityName   = null;
    protected static array $specialFieldOnlyUpdate    = [];
    protected static array $editEntitiesSkipFields    = [];
    protected static array $overviewSingleNotShown    = [];
    protected static array $overviewGeneralNotShown   = [];

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
            /** @var DOMElement $node */
            foreach ($crawler->filter('a') as $node) {
                if (str_contains($node->nodeValue, $identifier)) {
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

    public function helperCheckExistence(string $id, mixed $value, array $element): void
    {
        if (in_array($id, static::$addEntitiesShown)) {
            $tmpValue = $element[$id];
            if (is_bool($value)) {
                $tmpValue = $value ? 'yes' : 'no';
            }
            self::assertSelectorExists('body:contains("' . $tmpValue . '")');
        }
        if (in_array($id, static::$addEntitiesCount)) {
            /** @var array $item */
            $item = $element[$id];
            self::assertSelectorExists('body:contains("' . count($item) . '")');
        }
    }

    /**
     * @param array<string, string|bool|array<string, bool>> $element
     */
    protected function helperSubmitFields(array $element): Crawler {
        self::assertSelectorExists('a:contains(' . $this->addButton . ')');
        $formFields = [];
        foreach ($element as $id => $field) {
            // Skip elements which we cannot set yet.
            // We can not set checkboxes directly.
            // We can not set the fields set by JS directly.
            if (is_bool($field) || $id === static::$addPlus) {
                continue;
            }
            $formId = str_replace('.', '][', $id);
            $formFields[static::$addForm . $formId . "]"] = $field;
            // For LanguageController the values for external identifier should follow internal
            if (key_exists('langid', $element) && !key_exists('externalid', $element)) {
                $formFields[static::$addForm . 'externalid]'] = $element['langid'];
            }
        }
        $this->verifyPageResponse('GET', static::$baseUrl . static::$add, 200);
        $button = $this->client->getCrawler()->selectButton('Save');
        $form = $button->form($formFields, 'POST');
        $formName = str_replace('[', '', static::$addForm);
        foreach ($element as $id => $field) {
            // Set checkboxes
            if (!is_bool($field)) {
                continue;
            }
            if ($field) {
                $form[$formName][$id]->tick();
            } else {
                $form[$formName][$id]->untick();
            }
        }
        // Get the underlying object to inject elements not currently in the DOM.
        $rawValues = $form->getPhpValues();
        if (static::$addPlus !== null && key_exists(static::$addPlus, $element)) {
            $rawValues[$formName][static::$addPlus] = $element[static::$addPlus];
        }
        return $this->client->request($form->getMethod(), $form->getUri(), $rawValues, $form->getPhpFiles());
    }

    /**
     * Test that admin can add a new entity for this controller.
     * @dataProvider provideAddCorrectEntities
     */
    public function testCheckAddEntityAdmin(array $element, array $expected): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$add !== '') {
            $this->helperSubmitFields($element);
            $this->checkStatusAndFollowRedirect();
            foreach ($element as $key => $value) {
                if (!is_array($value) && !in_array($key, static::$overviewSingleNotShown)) {
                    self::assertSelectorExists('body:contains("' . $value . '")');
                }
            }
            $this->verifyPageResponse('GET', static::$baseUrl, 200);
            foreach ($expected as $id => $value) {
                if (in_array($id, static::$overviewGeneralNotShown)) {
                    continue;
                }
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

    /**
     * Test failures when the admin provides wrong data.
     * @dataProvider provideAddFailureEntities
     */
    public function testCheckAddEntityAdminFailure(array $element, string $message): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->client->followRedirects(true);
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$add !== '') {
            $this->helperSubmitFields($element);
            self::assertNotEquals(500, $this->client->getResponse()->getStatusCode());
            self::assertSelectorExists('body:contains("'.$message.'")');
        }
    }

    /**
     * Test that admin can add edit an entity for this controller.
     *
     * @dataProvider provideEditCorrectEntities
     */
    public function testCheckEditEntityAdminCorrect(array $formDataKeys, array $formDataValues): void
    {
        if (static::$addPlus != '') {
            static::markTestSkipped('Edit not implemented yet for ' . static::$shortTag . '.');
        }
        $editLink = null;
        $formFields = [];
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixtures(static::$deleteFixtures);
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$edit !== '') {
            $singlePageLink = null;
            $this->client->followRedirects(true);
            $crawler = $this->getCurrentCrawler();
            /** @var DOMElement $node */
            foreach ($crawler->filter('a') as $node) {
                if (str_contains($node->nodeValue, static::$defaultEditEntityName)) {
                    $singlePageLink = $node->getAttribute('href');
                }
            }
            $this->verifyPageResponse('GET', $singlePageLink, 200);
            $crawler = $this->getCurrentCrawler();
            /** @var DOMElement $node */
            foreach ($crawler->filter('a') as $node) {
                if (str_contains($node->nodeValue, 'Edit')) {
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

    /**
     * Test that admin can edit an entity for this controller but receives an error when providing wrong data.
     *
     * @dataProvider provideEditFailureEntities
     */
    public function testCheckEditEntityAdminFailure(array $formDataKeys, array $formDataValues, string $message): void
    {
        if (static::$addPlus != '') {
            static::markTestSkipped('Edit not implemented yet for ' . static::$shortTag . '.');
        }
        $editLink = null;
        $formFields = [];
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixtures(static::$deleteFixtures);
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        if (static::$edit !== '') {
            $singlePageLink = null;
            $this->client->followRedirects(true);
            $crawler = $this->getCurrentCrawler();
            /** @var DOMElement $node */
            foreach ($crawler->filter('a') as $node) {
                if (str_contains($node->nodeValue, static::$defaultEditEntityName)) {
                    $singlePageLink = $node->getAttribute('href');
                }
            }
            $this->verifyPageResponse('GET', $singlePageLink, 200);
            $crawler = $this->getCurrentCrawler();
            /** @var DOMElement $node */
            foreach ($crawler->filter('a') as $node) {
                if (str_contains($node->nodeValue, 'Edit')) {
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
            self::assertSelectorExists('body:contains("'.$message.'")');
        }
    }

    public function provideAddCorrectEntities(): Generator
    {
        $entities = static::$addEntities;
        foreach ($entities as $element) {
            [$combinedValues, $element] = $this->helperProvideMergeAddEntity($element);
            [$combinedValues, $element] = $this->helperProvideTranslateAddEntity($combinedValues, $element);
            yield [$combinedValues, $element];
        }
    }

    public function provideAddFailureEntities(): Generator
    {
        $entities = static::$addEntitiesFailure;
        foreach ($entities as $message => $elementList) {
            foreach ($elementList as $element) {
                [$entity, $expected] = $this->helperProvideMergeAddEntity($element);
                [$entity, $dropped] = $this->helperProvideTranslateAddEntity($entity, $expected);
                yield [$entity, $message];
            }
        }
    }

    protected function helperProvideMergeAddEntity(array $overWriteValues): array
    {
        $combinedValues = [];
        // First fill with default values, the 0th item of the array
        // Overwrite with data to test with.
        foreach ([static::$addEntities[0], $overWriteValues] as $item) {
            foreach ($item as $id => $field) {
                $combinedValues[$id] = $field;
            }
        }
        return [$combinedValues, $overWriteValues];
    }

    protected function helperProvideTranslateAddEntity(array $entity, array $expected): array
    {
        return [$entity, $expected];
    }

    public function helperProvideMergeEditEntity(array $element): array
    {
        $formdataKeys = [];
        $formdataValues = [];
        foreach (static::$addEntities[0] as $key => $value) {
            if (!in_array($key, static::$editEntitiesSkipFields)) {
                $formdataKeys[] = $key;
                // There are some special fields like passwords which we only update when set.
                if (in_array($key, static::$specialFieldOnlyUpdate)) {
                    $value = '';
                }
                $formdataValues[] = $element[$key] ?? $value;
            }
        }
        return [$formdataKeys, $formdataValues];
    }

    public function provideEditCorrectEntities(): Generator
    {
        foreach (static::$addEntities as $element) {
            [$formdataKeys, $formdataValues] = $this->helperProvideMergeEditEntity($element);
            yield [$formdataKeys, $formdataValues];
        }
    }

    public function provideEditFailureEntities(): Generator
    {
        /* The first key in the array:
           [$message => [[$offending_key => $offending_value, $other_key => $other_values...]]]
           is expected to have the offending value, when this is defined in $editEntitiesSkipFields
           we skip this */
        foreach (static::$addEntitiesFailure as $message => $entityList) {
            foreach ($entityList as $element) {
                if (in_array(array_key_first($element), static::$editEntitiesSkipFields)) {
                    continue;
                }
                if (key_exists('externalid', $element)) {
                    continue;
                }
                [$formdataKeys, $formdataValues] = $this->helperProvideMergeEditEntity($element);
                yield [$formdataKeys, $formdataValues, $message];
            }
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
            self::markTestSkipped('Test skipped');
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
