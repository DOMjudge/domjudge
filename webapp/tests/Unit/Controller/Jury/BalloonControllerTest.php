<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;
use Generator;

class BalloonControllerTest extends BaseTest
{
    protected array $roles = ['jury'];

    /**
     * Test that some roles can not access balloons page.
     *
     * @dataProvider provideRoleNoBalloonAccess
     */
    public function testNoBalloonsAccessForRole(string $role): void
    {
        $this->roles = [$role];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury/balloons', 403);
    }

    /**
     * Test that some roles can access balloons page.
     *
     * @dataProvider provideRoleBalloonAccess
     */
    public function testBalloonsAccessForRole(string $role): void
    {
        $this->roles = [$role];
        $this->logOut();
        $this->logIn();
        $this->verifyPageResponse('GET', '/jury/balloons', 200);
        self::assertSelectorExists('h1:contains("Balloons - Demo contest")');

        // Test database does not contain balloon info.
        self::assertSelectorExists('div.alert:contains("No balloons")');
    }

    public function provideRoleNoBalloonAccess(): Generator
    {
        yield ['team'];
        yield ['clarification_rw'];
        yield ['jury'];
    }

    public function provideRoleBalloonAccess(): Generator
    {
        yield ['balloon'];
        yield ['admin'];
    }
}
