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
     * @param string[] $expectedRows
     */
    #[DataProvider('provideRunDiff')]
    public function testRunDiff(string $team, string $reference, array $expectedRows): void
    {
        $html = $this->twigExtension->runDiff([
            'output_run'       => $team,
            'output_reference' => $reference,
        ]);

        self::assertSame($expectedRows, self::diffRows($html));
    }

    public static function provideRunDiff(): Generator
    {
        // Lines the reference output does not have are shown as removed, and the other way
        // around: a differing number of lines is a difference and must be visible.
        yield 'team has extra lines' => ["1\n2\n3", "1", [
            '1: 1',
            '2: <del>2</del> <ins></ins>',
            '3: <del>3</del> <ins></ins>',
        ]];
        yield 'team misses lines' => ["1", "1\n2\n3", [
            '1: 1',
            '2: <del></del> <ins>2</ins>',
            '3: <del></del> <ins>3</ins>',
        ]];

        // Without a difference to center the window on, show everything rather than a table
        // holding nothing but "[...]" markers.
        $tenLines = implode("\n", range(1, 10));
        yield 'identical outputs' => [$tenLines, $tenLines, [
            '1: 1', '2: 2', '3: 3', '4: 4', '5: 5',
            '6: 6', '7: 7', '8: 8', '9: 9', '10: 10',
        ]];

        // A difference in the middle keeps five lines of context on either side.
        $twentyLines = implode("\n", range(1, 20));
        yield 'difference in the middle' => [
            str_replace("\n7\n", "\nWRONG\n", $twentyLines),
            $twentyLines,
            [
                '[...]: ',
                '2: 2', '3: 3', '4: 4', '5: 5', '6: 6',
                '7: <del>WRONG</del> <ins>7</ins>',
                '8: 8', '9: 9', '10: 10', '11: 11', '12: 12',
                '[...]: ',
            ],
        ];
    }

    /**
     * Reduce a rendered diff table to one "linenr: content" entry per row.
     *
     * @return string[]
     */
    private static function diffRows(string $html): array
    {
        preg_match_all(
            '~<tr><td class="linenr">(.*?)</td><td>(.*?)</td></tr>~s',
            $html, $matches, PREG_SET_ORDER
        );

        return array_map(fn(array $match): string => $match[1] . ': ' . trim($match[2]), $matches);
    }

    /**
     * @param string[] $expectedRows
     */
    #[DataProvider('provideInteractiveLog')]
    public function testInteractiveLog(string $log, bool $forTeam, array $expectedRows): void
    {
        $html = $this->twigExtension->interactiveLog($log, forTeam: $forTeam);

        self::assertSame($expectedRows, self::interactiveLogRows($html));
    }

    public static function provideInteractiveLog(): Generator
    {
        // A message as runpipe writes it: header, direction character, ": ", the message
        // itself and a newline. The header records the size of the message only.
        $message = static fn(string $time, string $message, string $direction): string
            => sprintf("[%s/%d]%s: %s\n", $time, strlen($message), $direction, $message);
        // An EOF marker is only a header and a direction character, with no message at all.
        $eof = static fn(string $time, string $direction): string
            => sprintf("[%s/0]%s", $time, $direction);

        yield 'normal exchange' => [
            'log'          => $message('  0.001s', "5\n", '>') . $message('  0.002s', "7\n", '<')
                                  . $message('  0.123s', "ok\n", '>'),
            'forTeam'      => false,
            'expectedRows' => [
                "  0.001s | 5\u{21B5}<br/> | ",
                "  0.002s |  | 7\u{21B5}<br/>",
                "  0.123s | ok\u{21B5}<br/> | ",
            ],
        ];

        // An EOF marker is shorter than a message with an empty body, so skipping it as if it
        // were one ate the first characters of the next header, mangling its timestamp.
        yield 'EOF marker in the middle' => [
            'log'          => $message('  0.001s', "5\n", '>') . $eof('  0.002s', ']')
                                  . $message('  0.003s', "9\n", '<'),
            'forTeam'      => false,
            'expectedRows' => [
                "  0.001s | 5\u{21B5}<br/> | ",
                '  0.002s | EOF from program | ',
                "  0.003s |  | 9\u{21B5}<br/>",
            ],
        ];

        // "0" is a perfectly fine message, but it is falsy: it used to end the whole table.
        yield 'message of a single zero byte' => [
            'log'          => $message('  0.001s', '0', '<') . $message('  0.002s', "done\n", '>'),
            'forTeam'      => false,
            'expectedRows' => [
                '  0.001s |  | 0',
                "  0.002s | done\u{21B5}<br/> | ",
            ],
        ];

        // A log cut off mid-message still shows what is left of it.
        yield 'log cut off mid-message' => [
            'log'          => $message('  0.001s', "5\n", '>') . '[  0.002s/10]<: trunc',
            'forTeam'      => false,
            'expectedRows' => [
                "  0.001s | 5\u{21B5}<br/> | ",
                '  0.002s |  | trunc',
            ],
        ];

        // Teams do not get the timing column.
        yield 'for team' => [
            'log'          => $message('  0.001s', "5\n", '>') . $message('  0.002s', "7\n", '<'),
            'forTeam'      => true,
            'expectedRows' => [
                "5\u{21B5}<br/> | ",
                " | 7\u{21B5}<br/>",
            ],
        ];
    }

    /**
     * Reduce the rendered log table to one entry per row, cells separated by " | ".
     *
     * @return string[]
     */
    private static function interactiveLogRows(string $html): array
    {
        preg_match_all('~<tr>((?:<td.*?</td>)+)</tr>~s', $html, $matches);

        return array_map(static function (string $row): string {
            preg_match_all('~<td[^>]*>(.*?)</td>~s', $row, $cells);
            return implode(' | ', $cells[1]);
        }, $matches[1]);
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
