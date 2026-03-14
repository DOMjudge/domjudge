<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Tests\Unit\BaseTestCase;

class SubmitPageTest extends BaseTestCase
{
    protected array $roles = ['team'];

    public function testSubmitPageDefaultShowsUploadForm(): void
    {
        $this->verifyPageResponse('GET', '/team/submit', 200);
        $content = $this->client->getResponse()->getContent();

        // Default config has 'upload' only, so upload form should be present
        static::assertStringContainsString('submit_problem', $content);
    }

    public function testSubmitPageUploadOnlyHasNoTabs(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $crawler = $this->getCurrentCrawler();

            // No tab UI should be present
            static::assertCount(0, $crawler->filter('#upload-tab-page'));
            static::assertCount(0, $crawler->filter('#paste-tab-page'));
        });
    }

    public function testSubmitPagePasteOnlyShowsPasteForm(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $crawler = $this->getCurrentCrawler();

            // Paste form should be present
            static::assertCount(1, $crawler->filter('form[name="submit_problem_paste"]'));
            // Upload form should not be present
            static::assertCount(0, $crawler->filter('form[name="submit_problem"]'));
            // No file upload input
            static::assertCount(0, $crawler->filter('#submit_problem_code'));
        });
    }

    public function testSubmitPageBothMethodsShowsTabs(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $content = $this->client->getResponse()->getContent();

            static::assertStringContainsString('upload-tab-page', $content);
            static::assertStringContainsString('paste-tab-page', $content);
            static::assertStringContainsString('Upload File', $content);
            static::assertStringContainsString('Paste Code', $content);
        });
    }

    public function testSubmitPageBothMethodsHasUploadAndPasteForms(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $content = $this->client->getResponse()->getContent();

            // Upload form
            static::assertStringContainsString('submit_problem', $content);
            // Paste form
            static::assertStringContainsString('submit_problem_paste', $content);
        });
    }

    public function testSubmitModalOnlyHasUploadForm(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            // AJAX request returns the modal
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Modal should have upload form
            static::assertStringContainsString('submit_problem', $content);
            // Modal should NOT have paste form (paste is full-page only)
            static::assertStringNotContainsString('submit_problem_paste_code_content', $content);
        });
    }

    public function testSubmitModalPasteOnlyShowsFallbackLink(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Should show fallback message linking to the full page
            static::assertStringContainsString('full submit page', $content);
        });
    }

    public function testSubmitModalBothMethodsShowsPasteTabLink(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Modal should have a tab linking to the paste page
            static::assertStringContainsString('Paste Code', $content);
            static::assertStringContainsString('tab=paste', $content);
        });
    }

    public function testMenuShowsUploadModalWhenUploadEnabled(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $content = $this->client->getResponse()->getContent();

            static::assertStringContainsString('data-ajax-modal', $content);
        });
    }

    public function testMenuShowsBothSubmitButtonsWhenBothMethodsEnabled(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $crawler = $this->getCurrentCrawler();

            // Modal button (default, visible)
            static::assertCount(1, $crawler->filter('#submit-btn-modal'));
            // Paste direct link (hidden by default, shown via JS when cookie is set)
            static::assertCount(1, $crawler->filter('#submit-btn-paste'));
        });
    }

    public function testMenuShowsDirectLinkWhenPasteOnly(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $content = $this->client->getResponse()->getContent();

            // Should link directly to submit page, not use modal
            $crawler = $this->getCurrentCrawler();
            $submitLinks = $crawler->filter('a[href*="team/submit"]');
            static::assertGreaterThan(0, $submitLinks->count());

            // Should NOT have modal trigger
            $modalLinks = $crawler->filter('a[data-ajax-modal][href*="team/submit"]');
            static::assertCount(0, $modalLinks);
        });
    }
}
