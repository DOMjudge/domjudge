<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;

class JuryMiscControllerTest extends BaseTest
{
    protected $roles = ['jury'];

    /**
     * Test that if no user is logged in the user gets redirected to the login page
     */
    public function testJuryRedirectToLogin() : void
    {
        $this->logOut();

        $this->verifyPageResponse('GET', '/jury', 302, 'http://localhost/login');
    }

    /**
     * Test the login process for a jury member
     */
    public function testLogin(): void
    {
        $this->logOut();

        // Make sure the suer has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('demo', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('demo', 'demo', 'http://localhost/jury', 200);
    }

    /**
     * Test that the jury index page works
     */
    public function testJuryIndexPage(): void
    {
        $this->client->request('GET', '/jury');

        $this->verifyPageResponse('GET', '/jury', 200);
        self::assertSelectorExists('html:contains("DOMjudge Jury interface")');
    }
}
