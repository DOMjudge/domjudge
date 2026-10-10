<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Jury;

use App\Service\ConfigurationService;
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

        self::assertSelectorExists('label:contains("Memory limit")');
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

    public function testSaveWithoutChanges(): void
    {
        $this->submitConfig(fn(array $config) => $config);
        $this->checkStatusAndFollowRedirect();
        self::assertSelectorExists('div.alert-warning:contains("No changes made, no changes saved.")');
    }

    public function testSaveChangedMemoryLimit(): void
    {
        $this->submitConfig(fn(array $config) => ['memory_limit' => '123456'] + $config);
        $this->checkStatusAndFollowRedirect();
        self::assertSelectorExists('div.alert-info li:contains("memory_limit")');
        self::assertSame(123456, $this->getConfig('memory_limit'));
    }

    public function testSaveInvalidMemoryLimit(): void
    {
        $this->submitConfig(fn(array $config) => ['memory_limit' => '-1'] + $config);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('a.nav-link.active:contains("Judging")');
        self::assertSelectorExists('.invalid-feedback:contains("A positive number is required.")');
        self::assertSame(2097152, $this->getConfig('memory_limit'));
    }

    public function testUncheckBoolean(): void
    {
        self::assertTrue($this->getConfig('enable_ranking'));
        $this->submitConfig(function (array $config) {
            unset($config['enable_ranking']);
            return $config;
        });
        $this->checkStatusAndFollowRedirect();
        self::assertFalse($this->getConfig('enable_ranking'));
    }

    public function testClearArrayValue(): void
    {
        self::assertNotEmpty($this->getConfig('clar_answers'));
        $this->submitConfig(function (array $config) {
            unset($config['clar_answers']);
            return $config;
        });
        $this->checkStatusAndFollowRedirect();
        self::assertSame([], $this->getConfig('clar_answers'));
    }

    public function testAddMultipleKeyValueRows(): void
    {
        $this->submitConfig(function (array $config) {
            $config['clar_categories'][5] = ['key' => 'first', 'val' => 'First'];
            $config['clar_categories'][6] = ['key' => 'second', 'val' => 'Second'];
            return $config;
        });
        $this->checkStatusAndFollowRedirect();
        self::assertSame([
            'general' => 'General issue',
            'tech' => 'Technical issue',
            'first' => 'First',
            'second' => 'Second',
        ], $this->getConfig('clar_categories'));
    }

    public function testResultsPrioStoredAsIntegers(): void
    {
        $this->submitConfig(function (array $config) {
            $config['results_prio'][0]['val'] = '50';
            return $config;
        });
        $this->checkStatusAndFollowRedirect();
        $resultsPrio = $this->getConfig('results_prio');
        self::assertSame(50, $resultsPrio['memory-limit']);
        self::assertSame(1, $resultsPrio['correct']);
    }

    public function testResultsPrioRejectsNonInteger(): void
    {
        $this->submitConfig(function (array $config) {
            $config['results_prio'][0]['val'] = 'abc';
            return $config;
        });
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('a.nav-link.active:contains("Scoring")');
        self::assertSame(99, $this->getConfig('results_prio')['memory-limit']);
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $modify
     */
    private function submitConfig(callable $modify): void
    {
        $this->verifyPageResponse('GET', '/jury/config', 200);
        $form = $this->getCurrentCrawler()->selectButton('Save all changes')->form();
        $values = $form->getPhpValues();
        $values['config'] = $modify($values['config']);
        $this->client->request($form->getMethod(), $form->getUri(), $values);
    }

    private function getConfig(string $name): mixed
    {
        return self::getContainer()->get(ConfigurationService::class)->get($name);
    }
}
