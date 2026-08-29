<?php declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Twig\TwigExtension;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class TwigExtensionTest extends TestCase
{
    private TwigExtension $twigExtension;

    protected function setUp(): void
    {
        $this->twigExtension = new TwigExtension(
            $this->createMock(DOMJudgeService::class),
            $this->createMock(ConfigurationService::class),
            $this->createMock(Environment::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(SubmissionService::class),
            $this->createMock(EventLogService::class),
            $this->createMock(AwardService::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(AuthorizationCheckerInterface::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(SerializerInterface::class),
            '/tmp'
        );
    }

    public function testPrintMetadataNull(): void
    {
        self::assertSame('', $this->twigExtension->printMetadata(null));
    }

    public function testPrintMetadataValid(): void
    {
        $metadata = "cpu-time: 0.123\nwall-time: 0.456\nmemory-bytes: 1048576\nexitcode: 0\nsignal: 0";
        $html = $this->twigExtension->printMetadata($metadata);

        self::assertStringContainsString('0.123s CPU', $html);
        self::assertStringContainsString('0.456s wall', $html);
        self::assertStringContainsString('1 MB', $html);
        self::assertStringContainsString('exit-code: 0', $html);
        self::assertStringContainsString('signal: 0', $html);
    }

    public function testPrintMetadataEscapesHtmlPayload(): void
    {
        $payload = '<img src=x onerror="alert(1)">';
        $metadata = "cpu-time: {$payload}\nwall-time: {$payload}\nexitcode: {$payload}\nsignal: {$payload}";
        $html = $this->twigExtension->printMetadata($metadata);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }

    /**
     * Every notation Utils::convertToHex() accepts must render, including the
     * one and three digits per channel forms.
     */
    #[DataProvider('provideHexColorToRGBA')]
    public function testHexColorToRGBA(string $color, float $opacity, string $expected): void
    {
        self::assertSame($expected, $this->twigExtension->hexColorToRGBA($color, $opacity));
    }

    public static function provideHexColorToRGBA(): Generator
    {
        // The same colour in one, two and three hex digits per channel.
        yield ['#abc', 1, 'rgba(170,187,204,1)'];
        yield ['#aabbcc', 1, 'rgba(170,187,204,1)'];
        yield ['#aaabbbccc', 1, 'rgba(170,187,204,1)'];

        // The opacity is the caller's to decide, the colour carries none.
        yield ['#aabbcc', 0, 'rgba(170,187,204,0)'];
        yield ['#aabbcc', 0.5, 'rgba(170,187,204,0.5)'];

        // Colour names go through the same path.
        yield ['firebrick', 1, 'rgba(178,34,34,1)'];

        // Anything convertToHex() rejects is passed through untouched.
        yield ['doesnotexist', 1, 'doesnotexist'];
        yield ['#aabbccdd', 1, '#aabbccdd'];
    }
}
