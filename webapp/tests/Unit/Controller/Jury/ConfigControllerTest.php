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

        self::assertSelectorExists('label:contains("Memory limit:")');
        self::assertSelectorExists('p:contains("Maximum memory usage (in kB) by submissions. This includes the shell which starts the compiled solution and also any interpreter like the Java VM, which takes away approx. 300MB! Can be overridden per problem.")');
        $crawler = $this->getCurrentCrawler();
        $memoryLimit = $crawler->filter('input#config_memory_limit')->extract(['value']);
        self::assertEquals("2097152", $memoryLimit[0]);
    }

    /**
     * Test that a different memory limit shows up in this page.
     */
    public function testChangedMemoryLimit(): void
    {
        $this->withChangedConfiguration('memory_limit', "123456",
            function ($errors): void {
                static::assertEmpty($errors);
                $this->verifyPageResponse('GET', '/jury/config', 200);
                $crawler = $this->getCurrentCrawler();
                $memoryLimit = $crawler->filter('input#config_memory_limit')->extract(['value']);
                static::assertEquals("123456", $memoryLimit[0]);
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
     * Test that an invalid memory limit produces an error
     */
    public function testChangedMemoryLimitInvalid(): void
    {
        $this->withChangedConfiguration('memory_limit', "-1",
            function ($errors): void {
                static::assertEquals(['memory_limit' => 'A positive number is required.'], $errors);
                $this->verifyPageResponse('GET', '/jury/config', 200);
                $crawler = $this->getCurrentCrawler();
                $memoryLimit = $crawler->filter('input#config_memory_limit')->extract(['value']);
                // test that it is still 20, i.e. it didn't change
                static::assertEquals("2097152", $memoryLimit[0]);
            });
    }
}
