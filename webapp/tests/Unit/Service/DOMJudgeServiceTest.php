<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\DOMJudgeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class DOMJudgeServiceTest extends KernelTestCase
{
    private const ASSET_PATH = 'test-assets';

    private DOMJudgeService $dj;
    private string $assetDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->dj       = static::getContainer()->get(DOMJudgeService::class);
        $this->assetDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/' . self::ASSET_PATH;
        (new Filesystem())->mkdir($this->assetDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->assetDir);
        parent::tearDown();
    }

    public function testGetAssetFilesMatchesOnTheExtensionOnly(): void
    {
        foreach (['ok.css', 'also-ok.css', 'backup.css.bak', 'data.json', 'script.js', 'image.png'] as $file) {
            touch($this->assetDir . '/' . $file);
        }

        self::assertSame(['also-ok.css', 'ok.css'], $this->dj->getAssetFiles(self::ASSET_PATH, ['css']));
        self::assertSame(['image.png', 'script.js'], $this->dj->getAssetFiles(self::ASSET_PATH, ['png', 'js']));
    }
}
