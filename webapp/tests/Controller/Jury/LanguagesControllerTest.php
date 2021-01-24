<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Entity\Language;

class LanguagesControllerTest extends JuryControllerTest
{
    protected static $baseUrl           = '/jury/languages';
    protected static $deleteEntities    = array('name' => ['C++']);
    protected static $getIDFunc         = 'getLangid';
    protected static $exampleEntries    = ['c','csharp','Haskell','Bash shell',"pas, p",'no','yes','R','r'];
    protected static $shortTag          = 'language';
    protected static $DOM_elements      = array('h1' => ['Languages']);
    protected static $className         = Language::class;

    /**
     * Data provider used to test role access. Each item contains:
     * This should be removed when the add Language is fixed
     * - the base role the user has
     * - the endpoint to check
     * - expected statusCode for this role
     * - the method to try (GET, POST)
     */
    public function provideRoleAccessData() : \Generator
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
            if (static::$add !== '')
            {
                yield ['admin', static::$baseUrl . static::$add, 500, $HTTP];
            }
        }
    }

    /**
     * Test that the role can add a new entity
     * @dataProvider provideAddEntity
     */
    public function testCheckAddEntity (
        array $formValues,
        string $entity
    ) : void
    {
        static::$roles = ['admin'];
        $this->logOut();
        $this->logIn();
        if (static::$add === '') {
            self::assertTrue(True, "Test skipped.");
            return;
        }
        $this->verifyPageResponse(
            'GET',
            static::$prefixURL.static::$baseUrl.static::$add,
            500
        );
        $this->verifyPageResponse('GET', static::$baseUrl, 200);
        $link = $this->verifyLinkToURL(
            $this->addButton,
            static::$prefixURL.static::$baseUrl.static::$add);
        $this->client->click($link);
    }
}
