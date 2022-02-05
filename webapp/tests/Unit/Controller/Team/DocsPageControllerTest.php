<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Tests\Unit\BaseTest;

class DocsPageControllerTest extends BaseTest
{
    protected array $roles = ['team'];

    protected const YAML = __DIR__ . '/../../../../../etc/docs.yaml';

    protected function setUp(): void
    {
        copy(self::YAML . ".dist", self::YAML);
        $this->removeTestContainer();

        parent::setUp();
    }

    /**
     * Test that having docs.yaml does show docs link.
     */
    public function testDocsLinkInMenu(): void
    {
        $this->verifyPageResponse('GET', '/team', 200);

        self::assertSelectorExists('a:contains("Docs")');
    }

    /**
     * Test content of docs page shows items from docs.yaml.
     */
    public function testDocsPage(): void
    {
        $this->verifyPageResponse('GET', '/team/docs', 200);

        $crawler = $this->getCurrentCrawler();
        $links = $crawler->filter('.list-group a');

        $manual = $links->eq(0);
        self::assertEquals('../docs/team.pdf', $manual->attr('href'));
        self::assertStringContainsString('Team guide', $manual->text('', false));
        $stl = $links->eq(1);
        self::assertEquals('https://www.cplusplus.com/reference/stl/', $stl->attr('href'));
        self::assertStringContainsString('C++ STL', $stl->text('', false));
    }

    protected function tearDown(): void
    {
        unlink(self::YAML);
        $this->removeTestContainer();

        parent::tearDown();
    }
}
