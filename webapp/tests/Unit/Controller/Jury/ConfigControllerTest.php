<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Tests\Unit\BaseTestCase;

class ConfigControllerTest extends BaseTestCase
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
        self::assertSelectorExists('div.card-body:contains("Validated all languages.")');

        // We've reached the end of the page.
        self::assertSelectorExists('div:contains("All checks complete.")');
        self::assertSelectorExists('details li:contains("checkTeamDuplicateNames")');
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
            function ($errors): void {
                static::assertEmpty($errors);
                $this->verifyPageResponse('GET', '/jury/config', 200);
                $crawler = $this->getCurrentCrawler();
                $minutes = $crawler->filter('input#config_penalty_time')->extract(['value']);
                static::assertEquals("30", $minutes[0]);
            });
    }

    /**
     * Test that we can change a longer config value.
     */
    public function testChangedLongConfigName(): void
    {
        $this->withChangedConfiguration('config_external_contest_sources_allow_untrusted_certificates', 'on',
            function ($errors): void {
                static::assertEmpty($errors);
                $this->verifyPageResponse('GET', '/jury/config', 200);
            });
    }

    /**
     * Test that an invalid penalty time produces an error
     */
    public function testChangedPenaltyTimeInvalid(): void
    {
        $this->withChangedConfiguration('penalty_time', "-1",
            function ($errors): void {
                static::assertEquals(['penalty_time' => 'A non-negative number is required.'], $errors);
                $this->verifyPageResponse('GET', '/jury/config', 200);
                $crawler = $this->getCurrentCrawler();
                $minutes = $crawler->filter('input#config_penalty_time')->extract(['value']);
                // test that it is still 20, i.e. it didn't change
                static::assertEquals("20", $minutes[0]);
            });
    }
}
