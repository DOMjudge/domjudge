<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Entity\BaseApiEntity;
use App\Tests\Unit\BaseTest;
use Doctrine\ORM\EntityManager;
use Generator;

/**
 * Class JuryControllerTest
 *
 * This abstract class will have the default functionality tested for Jury pages
 *
 * @package App\Tests\Unit\Controller\Jury
 */
abstract class JuryControllerTest extends BaseTest
{
    protected        $roles             = ['admin'];
    protected        $addButton         = '';
    protected static $rolesView         = ['admin','jury'];
    protected static $rolesDisallowed   = ['team'];
    protected static $exampleEntries    = ['overwrite_in_class'];
    protected static $prefixURL         = 'http://localhost';
    protected static $add               = '/add';
    protected static $edit              = '/edit';
    protected static $delete            = '/delete';
    protected static $deleteEntities    = [];
    protected static $deleteFixtures    = [];
    protected static $shortTag          = '';
    protected static $addPlus           = null;
    protected static $addForm           = '';
    protected static $deleteExtra       = null;
    protected static $addEntities       = [];
    protected static $addEntitiesCount  = [];

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->addButton = ' Add new ' . static::$shortTag;
    }

    /**
     * @var String[]|array[] $DOM_elements
     */
    protected static $DOM_elements;

    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * @var BaseApiEntity $className ;
     */
    protected static $className;

    /**
     * @var string $getIDFunc ;
     */
    protected static $getIDFunc;

    /**
     * Test that jury <???> overview page exists
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
     * - the base role the user has
     * - the endpoint to check
     * - expected statusCode for this role
     * - the method to try (GET, POST)
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
     * - the base role of the user
     * - the expected HTTP statusCode
     * - the pre-existing entry
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
     * Test that jury role can NOT add a new entity for this controller
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

    public function helperCheckExistance(string $id, $value, array $element): void {
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
     * Test that admin can add a new entity for this controller
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
                $formFields = [];
                // First fill with default values, the 0th item of the array
                // Overwrite with data to test with.
                foreach ([static::$addEntities[0], $element] as $item) {
                    foreach ($item as $id=>$field) {
                        // Skip elements which we cannot set yet
                        // We can not set checkboxes directly
                        // We can not set the fields set by JS directly
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
            $this->verifyPageResponse('GET', static::$baseUrl, 200);
            foreach (static::$addEntities as $element) {
                foreach ($element as $id=>$value) {
                    if (is_array($value)) {
                        if (in_array($id, static::$addEntitiesCount)) {
                            self::assertSelectorExists('body:contains("' . count($element[$id]) . '")');
                        } else {
                            foreach ($value as $id2=>$value2) {
                                if (is_array($value2)) {
                                    $this->helperCheckExistance((string)$id, $value2, $element);
                                }
                            }
                        }
                    } else {
                        $this->helperCheckExistance($id, $value, $element);
                    }
                }
            }
        }
    }

    /**
     * Test that the standard user can delete an entity
     *
     * @dataProvider provideDeleteEntity
     */
    public function testDeleteEntity(string $identifier, string $entityShortName): void
    {
        $this->roles = ['admin'];
        $this->logOut();
        $this->logIn();
        $this->loadFixtures(static::$deleteFixtures);
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        // Find a CID we can delete
        $em = self::$container->get('doctrine')->getManager();
        $ent = $em->getRepository(static::$className)->findOneBy([$identifier => $entityShortName]);
        self::assertSelectorExists('i[class*=fa-trash-alt]');
        self::assertSelectorExists('body:contains("' . $entityShortName . '")');
        $this->verifyPageResponse(
            'GET',
            static::$baseUrl . '/' . $ent->{static::$getIDFunc}() . static::$delete,
            200
        );
        $this->client->submitForm('Delete', []);
        self::assertSelectorNotExists('body:contains("' . $entityShortName . '")');
    }

    /**
     * - entityShortname to delete
     */
    public function provideDeleteEntity(): Generator
    {
        if (static::$delete !== '') {
            foreach (static::$deleteEntities as $name => $entityList) {
                foreach ($entityList as $entity) {
                    yield [$name, $entity];
                }
            }
        } else {
            self::markTestSkipped("No deletable entity.");
        }
    }

    public function testDeleteExtraEntity(): void
    {
        if (static::$deleteExtra !== NULL) {
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
            self::assertTrue(True, "Test skipped");
        }
    }
}
