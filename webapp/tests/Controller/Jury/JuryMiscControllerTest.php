<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;

class JuryMiscControllerTest extends BaseTest
{
    protected static $roles = ['jury'];

    /**
     * Test that if no user is logged in the user gets redirected to the login page
     */
    public function testJuryRedirectToLogin()
    {
        $this->logOut();

        $this->verifyPageResponse('GET', '/jury', 302, 'http://localhost/login');
    }

    /**
     * Test the login process for a jury member
     */
    public function testLogin()
    {
        $this->logOut();

        // Make sure the suer has the correct permissions
        $this->setupUser();

        // test incorrect and correct password
        $this->loginHelper('dummy', 'foo', 'http://localhost/login', 200);
        $this->loginHelper('dummy', 'dummy', 'http://localhost/jury', 200);
    }

    /**
     * Test that the jury index poge works
     */
    public function testJuryIndexPage()
    {
        $this->client->request('GET', '/jury');

        $this->verifyPageResponse('GET', '/jury', 200);
        $this->assertSelectorExists('html:contains("DOMjudge Jury interface")');
    }
}
