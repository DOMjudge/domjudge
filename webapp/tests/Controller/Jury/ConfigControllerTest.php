<?php declare(strict_types=1);

namespace App\Tests\Controller\Jury;

use App\Tests\BaseTest;

class ConfigControllerTest extends BaseTest
{
    protected static $roles = ['admin'];

    /**
     * Test that configcheck page completes.
     */
    public function testConfgCheck()
    {
        $this->verifyPageResponse('GET', '/jury/config/check', 200);
        $this->assertSelectorExists(sprintf('div.card-body:contains("You have PHP version %s.")', PHP_VERSION));
        $this->assertSelectorExists('a:contains("Languages validation")');
        $this->assertSelectorExists('div.card-body:contains("Validated all languages:")');

        // We've reached the end of the page.
        $this->assertSelectorExists('div:contains("All checks complete.")');
    }

    /**
     * Test that phpinfo page completes.
     */
    public function testConfgCheckPhpInfo()
    {
        $this->verifyPageResponse('GET', '/jury/config/check/phpinfo', 200);
        $content = $this->client->getResponse()->getContent();
        $this->assertContains('This program makes use of the Zend Scripting Language Engine', $content);
        $this->assertContains('This program is free software', $content);
    }

    /**
     * Test that config settings page contains some expected options
     */
    public function testConfgSettingsPresent()
    {
        $this->verifyPageResponse('GET', '/jury/config', 200);

        $this->assertSelectorExists('div.card-header:contains("Scoring Options")');

        $this->assertSelectorExists('label:contains("Penalty time:")');
        $this->assertSelectorExists('small:contains("Penalty time in minutes per wrong submission (if finally solved).")');
        $crawler = $this->getCurrentCrawler();
        $minutes = $crawler->filter('input#config_penalty_time')->extract(['value']);
        $this->assertEquals("20", $minutes[0]);

        $this->assertSelectorExists('div.card-header:contains("Clarification Options")');

        $this->assertSelectorExists('label:contains("Clar default problem queue:")');

        $this->assertSelectorExists('div.card-header:contains("Misc Options")');

        $this->assertSelectorExists('label:contains("Print command:")');
        $command = $crawler->filter('input#config_print_command')->extract(['value']);
        $this->assertEquals("", $command[0]);
    }

    /**
     * Test that a different penalty time shows up in this page.
     */
    public function testChangedPenaltyTime()
    {
        $this->withChangedConfiguration('penalty_time', "30",
            function () {
                $this->verifyPageResponse('GET', '/jury/config', 200);
                $crawler = $this->getCurrentCrawler();
                $minutes = $crawler->filter('input#config_penalty_time')->extract(['value']);
                $this->assertEquals("30", $minutes[0]);
            });
    }
}
