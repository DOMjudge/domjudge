<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTest;

class ConfigControllerTest extends BaseTest
{
    protected array $roles = ['admin'];

    /**
     * Test that configcheck page completes.
     */
    public function testConfigCheck(): void
    {
        $this->verifyPageResponse('GET', '/jury/config/check', 200);
        self::assertSelectorExists(sprintf('div.card-body:contains("You have PHP version %s.")', PHP_VERSION));
        self::assertSelectorExists('a:contains("Languages validation")');
        self::assertSelectorExists('div.card-body:contains("Validated all languages:")');

        // We've reached the end of the page.
        self::assertSelectorExists('div:contains("All checks complete.")');
        self::assertSelectorExists('details li:contains("checkTeamDuplicateNames")');
    }

    /**
     * Test that phpinfo page completes.
     */
    public function testConfigCheckPhpInfo(): void
    {
        $this->verifyPageResponse('GET', '/jury/config/check/phpinfo', 200);
        $content = $this->client->getResponse()->getContent();
        self::assertStringContainsString('This program makes use of the Zend Scripting Language Engine', $content);
        self::assertStringContainsString('This program is free software', $content);
    }

    /**
     * Test that config settings page contains some expected options.
     */
    public function testConfigSettingsPresent(): void
    {
        $this->verifyPageResponse('GET', '/jury/config', 200);

        self::assertSelectorExists('a.nav-link:contains("Scoring")');

        self::assertSelectorExists('label:contains("Penalty time:")');
        self::assertSelectorExists('p:contains("Penalty time in minutes per wrong submission (if eventually solved).")');
        $crawler = $this->getCurrentCrawler();
        $minutes = $crawler->filter('input#config_penalty_time')->extract(['value']);
        self::assertEquals("20", $minutes[0]);
    }

    /**
     * Test that a different penalty time shows up in this page.
     */
    public function testChangedPenaltyTime(): void
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
