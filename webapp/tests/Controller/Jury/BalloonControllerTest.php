<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;

class BalloonControllerTest extends BaseTest
{
    protected static $roles = ['jury'];

    /**
     * Test that jury role can access balloons page.
     */
    public function testBalloonsAccessForJury()
    {
        $this->verifyPageResponse('GET', '/jury/balloons', 200);
        $this->assertSelectorExists('h1:contains("Balloons - Demo contest")');

        // Test database does not contain balloon info
        $this->assertSelectorExists('div.alert:contains("No balloons")');
    }
}
