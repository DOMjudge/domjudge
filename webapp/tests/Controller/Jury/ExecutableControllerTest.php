<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;
use Generator;

class ExecutableControllerTest extends BaseTest
{
    protected static $roles = ['admin'];

    /**
     * @param String $role
     * @param String $url
     * @param int $statusCode
     * @param String $HTTPMethod
     * @dataProvider provideRoleAccessData
     */
    public function testHTTPAccessForRole(string $role, string $url, int $statusCode, string $HTTPMethod)
    {
        static::$roles = [$role];
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
    public function provideRoleAccessData() : Generator
    {
        foreach (['GET', 'POST', 'HEAD'] as $HTTP)
        {
            foreach (['admin', 'jury'] as $role) {
                yield [$role, '/jury/executables', 200, $HTTP];
            }
            foreach (['team', 'jury'] as $role) {
                yield [$role, '/jury/executables/add', 403, $HTTP];
            }
            yield ['team', '/jury/executables', 403, $HTTP];
            yield ['admin', '/jury/executables/add', 200, $HTTP];
        }
    }
}
