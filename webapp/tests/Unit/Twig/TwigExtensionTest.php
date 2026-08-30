<?php declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\ContestProblem;
use App\Entity\ExternalSourceWarning;
use App\Entity\Team;
use App\Entity\Testcase as TestcaseEntity;
use App\Service\AwardService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Twig\TwigExtension;
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Scoreboard\TeamScore;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

/**
 * Filters marked `isSafe: ['html']` are put into the page as raw markup, so each has to escape
 * whatever it interpolates itself. The ones covered here render values we do not control:
 * testcase descriptions and filenames from imported problem archives, and warnings and
 * external ids the shadow importer writes straight from the remote CCS.
 *
 * `AppAssert\Identifier` looks like it makes those external ids safe, but it only applies when
 * the validator runs and `ExternalContestSourceService` never runs it.
 *
 * Not covered here, because they interpolate nothing we do not already constrain: `button` is
 * called with literals from our own templates, `codeEditor` with a language id that only an
 * admin can set through a validated form, and `countryFlag` with a code that has been resolved
 * against the ICU country list by the time it is used.
 */
class TwigExtensionTest extends TestCase
{
    /**
     * A payload that renders harmlessly as text but executes if it reaches the page as markup.
     */
    private const XSS_PAYLOAD = '<img src=x onerror="alert(1)">';

    private TwigExtension $twigExtension;
    private RouterInterface&MockObject $router;
    private SerializerInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->router     = $this->createMock(RouterInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

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
            $this->router,
            $this->serializer,
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
        $payload  = self::XSS_PAYLOAD;
        $metadata = "cpu-time: {$payload}\nwall-time: {$payload}\nexitcode: {$payload}\nsignal: {$payload}";
        $html     = $this->twigExtension->printMetadata($metadata);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
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
    public function testDescriptionExpandNull(): void
    {
        self::assertSame('', $this->twigExtension->descriptionExpand(null));
    }

    public function testDescriptionExpandTurnsNewlinesIntoLineBreaks(): void
    {
        self::assertSame(
            'first<br>second<br>third',
            $this->twigExtension->descriptionExpand("first\nsecond\nthird")
        );
    }

    /**
     * Testcase and run descriptions come out of imported problem archives, so a short
     * description must not be able to smuggle markup into the jury interface.
     */
    public function testDescriptionExpandEscapesShortDescription(): void
    {
        $html = $this->twigExtension->descriptionExpand(self::XSS_PAYLOAD);

        self::assertStringNotContainsString('<img', $html);
        self::assertSame('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }

    /**
     * Descriptions longer than three lines are collapsed. The visible part is written into
     * the document directly and both parts are stashed in data-attributes that
     * toggleExpand() assigns to innerHTML, so all three places need escaping.
     */
    public function testDescriptionExpandEscapesLongDescription(): void
    {
        $description = implode("\n", [self::XSS_PAYLOAD, 'two', 'three', 'four']);
        $html        = $this->twigExtension->descriptionExpand($description);

        self::assertStringNotContainsString('<img', $html);

        // The collapsed part shows the first three lines, the expanded part shows all four.
        self::assertMatchesRegularExpression('/data-collapsed="[^"]*two&lt;br&gt;three"/', $html);
        self::assertMatchesRegularExpression('/data-expanded="[^"]*three&lt;br&gt;four"/', $html);

        // The data-attributes end up as innerHTML, so they carry the payload escaped twice:
        // once so it survives as attribute text and once so innerHTML renders it as text.
        self::assertStringContainsString('&amp;lt;img src=x onerror=&amp;quot;alert(1)&amp;quot;&amp;gt;', $html);
    }

    public function testDescriptionExpandKeepsLongDescriptionReadable(): void
    {
        $html = $this->twigExtension->descriptionExpand("one\ntwo\nthree\nfour");

        self::assertStringContainsString('one<br>two<br>three', $html);
        self::assertStringContainsString('[expand]', $html);
    }

    /**
     * External source warnings are rendered from data supplied by the remote contest system,
     * including ZIP entry names taken from downloaded submissions.
     *
     * @param array<string, mixed> $content
     */
    #[DataProvider('provideWarningsWithPayload')]
    public function testPrintWarningContentEscapesContent(string $type, array $content): void
    {
        $warning = (new ExternalSourceWarning())
            ->setType($type)
            ->setContent($content);

        $html = $this->twigExtension->printWarningContent($warning);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }

    public static function provideWarningsWithPayload(): Generator
    {
        yield 'unsupported action' => [
            ExternalSourceWarning::TYPE_UNSUPORTED_ACTION,
            ['action' => self::XSS_PAYLOAD],
        ];
        yield 'data mismatch in field name' => [
            ExternalSourceWarning::TYPE_DATA_MISMATCH,
            ['diff' => [self::XSS_PAYLOAD => ['us' => 'a', 'external' => 'b']]],
        ];
        yield 'data mismatch in our value' => [
            ExternalSourceWarning::TYPE_DATA_MISMATCH,
            ['diff' => ['name' => ['us' => self::XSS_PAYLOAD, 'external' => 'b']]],
        ];
        yield 'data mismatch in external value' => [
            ExternalSourceWarning::TYPE_DATA_MISMATCH,
            ['diff' => ['name' => ['us' => 'a', 'external' => self::XSS_PAYLOAD]]],
        ];
        yield 'missing dependency type' => [
            ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            ['dependencies' => [['type' => self::XSS_PAYLOAD, 'id' => '1']]],
        ];
        yield 'missing dependency id' => [
            ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            ['dependencies' => [['type' => 'team', 'id' => self::XSS_PAYLOAD]]],
        ];
        yield 'submission error' => [
            ExternalSourceWarning::TYPE_SUBMISSION_ERROR,
            ['message' => 'Cannot create temporary file for ' . self::XSS_PAYLOAD],
        ];
    }

    /**
     * A missing value keeps its placeholder, which is pre-escaped and must not be escaped again.
     */
    public function testPrintWarningContentKeepsNullPlaceholder(): void
    {
        $warning = (new ExternalSourceWarning())
            ->setType(ExternalSourceWarning::TYPE_DATA_MISMATCH)
            ->setContent(['diff' => ['name' => ['us' => null, 'external' => 'other']]]);

        $html = $this->twigExtension->printWarningContent($warning);

        self::assertStringContainsString('<code>&lt;null&gt;</code>', $html);
        self::assertStringNotContainsString('&amp;lt;null&amp;gt;', $html);
        self::assertStringContainsString('<code>other</code>', $html);
    }


    public function testInteractiveLogRendersRows(): void
    {
        $html = $this->twigExtension->interactiveLog('[1.234/5]>: hello');

        self::assertStringContainsString('<td>1.234</td>', $html);
        self::assertStringContainsString('hello', $html);
    }

    /**
     * The log is parsed positionally, so a desynchronised parse can put arbitrary bytes from
     * the log into the time column.
     */
    public function testInteractiveLogEscapesTimeColumn(): void
    {
        $html = $this->twigExtension->interactiveLog('[' . self::XSS_PAYLOAD . '/5]>: hello');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }

    public function testInteractiveLogEscapesContent(): void
    {
        $payload = self::XSS_PAYLOAD;
        $html    = $this->twigExtension->interactiveLog(sprintf('[1.234/%d]>: %s', strlen($payload), $payload));

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }


    #[DataProvider('provideResults')]
    public function testPrintResultUsesExpectedStyle(string $result, bool $jury, string $expected): void
    {
        self::assertSame($expected, $this->twigExtension->printResult($result, true, $jury));
    }

    public static function provideResults(): Generator
    {
        yield 'correct' => ['correct', false, '<span class="sol sol_correct">correct</span>'];
        yield 'wrong answer' => ['wrong-answer', false, '<span class="sol sol_incorrect">wrong-answer</span>'];
        yield 'queued for a team' => ['queued', false, '<span class="sol sol_queued">pending</span>'];
        yield 'queued for the jury' => ['queued', true, '<span class="sol sol_queued">queued</span>'];
        yield 'internal error hidden from teams' => ['internal', false, '<span class="sol sol_queued">pending</span>'];
        yield 'internal error for the jury' => ['internal', true, '<span class="sol sol_internal">internal</span>'];
    }

    public function testPrintResultShowsPendingForEmptyResult(): void
    {
        self::assertSame('<span class="sol sol_queued">pending</span>', $this->twigExtension->printResult(null));
        self::assertSame(
            '<span class="sol sol_queued">judging</span>',
            $this->twigExtension->printResult(null, true, true)
        );
    }

    public function testPrintResultMarksInvalidJudgingsAsDisabled(): void
    {
        self::assertSame(
            '<span class="sol disabled">correct</span>',
            $this->twigExtension->printResult('correct', false)
        );
    }

    /**
     * Unknown results fall through to the default branch, which used to echo them verbatim.
     */
    public function testPrintResultEscapesUnknownResult(): void
    {
        $html = $this->twigExtension->printResult(self::XSS_PAYLOAD);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }

    public function testPrintValidJuryResultShowsJuryResult(): void
    {
        self::assertSame(
            '<span class="sol sol_internal">internal</span>',
            $this->twigExtension->printValidJuryResult('internal')
        );
    }

    #[DataProvider('provideStatuses')]
    public function testStatusIcon(string $status, string $expectedIcon): void
    {
        $html = TwigExtension::statusIcon($status);

        self::assertStringContainsString(sprintf('fa-%s-circle', $expectedIcon), $html);
        self::assertStringContainsString(sprintf('<span class="sr-only">%s</span>', $status), $html);
    }

    public static function provideStatuses(): Generator
    {
        yield ['noconn', 'question'];
        yield ['crit', 'times'];
        yield ['warn', 'exclamation'];
        yield ['ok', 'check'];
    }

    public function testStatusIconEscapesUnknownStatus(): void
    {
        $html = TwigExtension::statusIcon(self::XSS_PAYLOAD);

        self::assertStringNotContainsString('<img', $html);
        self::assertSame('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }



    public function testPrintHostEscapes(): void
    {
        $html = $this->twigExtension->printHost(self::XSS_PAYLOAD, true);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }


    public function testPrintHostsEscapes(): void
    {
        $html = $this->twigExtension->printHosts([self::XSS_PAYLOAD, 'judgehost']);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    /**
     * The testcase title lists the original input filename and the description, both of which
     * come out of the imported problem archive, and it is built into a title attribute. Its
     * sibling `testcaseResults` escapes the same description.
     */
    public function testDisplayTestcaseResultsEscapesTheTitle(): void
    {
        $html = $this->twigExtension->displayTestcaseResults(
            [$this->testcase(description: '" onmouseover="alert(1)')],
            false
        );

        self::assertStringNotContainsString('onmouseover', strip_tags($html));
        self::assertStringContainsString('&quot; onmouseover=&quot;alert(1)', $html);
    }

    public function testDisplayTestcaseResultsEscapesTheOriginalFilename(): void
    {
        $html = $this->twigExtension->displayTestcaseResults(
            [$this->testcase(filename: '" onmouseover="alert(1)')],
            false
        );

        self::assertStringContainsString('&quot; onmouseover=&quot;alert(1)', $html);
    }

    public function testDisplayTestcaseResultsShowsRankAndPendingState(): void
    {
        $html = $this->twigExtension->displayTestcaseResults(
            [$this->testcase(description: 'the description', filename: 'first.in')],
            false
        );

        self::assertStringContainsString('title="#1, name: first.in, desc: the description"', $html);
        self::assertStringContainsString('href="#run-1"', $html);
        self::assertStringContainsString('badge-testcase', $html);
    }

    private function testcase(string $description = '', string $filename = '', int $rank = 1): TestcaseEntity
    {
        $testcase = $this->createMock(TestcaseEntity::class);
        $testcase->method('getSample')->willReturn(true);
        $testcase->method('getFirstJudgingRun')->willReturn(null);
        $testcase->method('getRank')->willReturn($rank);
        $testcase->method('getOrigInputFilename')->willReturn($filename);
        $testcase->method('getDescription')->willReturn($description);

        return $testcase;
    }

    /**
     * Problem labels are only validated to be non-blank, and the badge is shown on the public
     * scoreboard.
     */
    public function testProblemBadgeEscapesTheShortname(): void
    {
        $problem = $this->createMock(ContestProblem::class);
        $problem->method('getColor')->willReturn('#ff0000');
        $problem->method('getShortname')->willReturn(self::XSS_PAYLOAD);

        $html = $this->twigExtension->problemBadge($problem);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
    }


    /**
     * An unknown colour falls back to a fixed one rather than reaching the style attribute.
     */
    public function testProblemBadgeIgnoresAnUnparseableColour(): void
    {
        $problem = $this->createMock(ContestProblem::class);
        $problem->method('getColor')->willReturn('"><script>alert(1)</script>');
        $problem->method('getShortname')->willReturn('A');

        $html = $this->twigExtension->problemBadge($problem);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('background-color: #F5F5F5', $html);
    }

    /**
     * The scoreboard variant of the badge escapes the same label, and additionally puts the
     * team and problem external ids into data-attributes.
     */
    public function testProblemBadgeMaybeEscapesTheShortnameAndIds(): void
    {
        $html = $this->problemBadgeMaybe(
            shortname: self::XSS_PAYLOAD,
            teamExternalId: '" onmouseover="alert(1)',
            problemExternalId: '" onfocus="alert(2)'
        );

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
        self::assertStringContainsString('data-team-id="&quot; onmouseover=&quot;alert(1)"', $html);
        self::assertStringContainsString('data-problem-id="&quot; onfocus=&quot;alert(2)"', $html);
    }



    private function problemBadgeMaybe(
        string $shortname,
        string $teamExternalId = 'team1',
        string $problemExternalId = 'problem1',
        bool $isCorrect = true,
        int $numSubmissions = 1,
        int $numPending = 0
    ): string {
        $this->router->method('generate')->willReturn('/submissions');

        $problem = $this->createMock(ContestProblem::class);
        $problem->method('getColor')->willReturn('#ff0000');
        $problem->method('getShortname')->willReturn($shortname);
        $problem->method('getExternalId')->willReturn($problemExternalId);

        $team = $this->createMock(Team::class);
        $team->method('getPenalty')->willReturn(0);
        $team->method('getExternalid')->willReturn($teamExternalId);

        return $this->twigExtension->problemBadgeMaybe(
            $problem,
            new ScoreboardMatrixItem($isCorrect, false, $numSubmissions, $numPending, 0, 0, 0),
            new TeamScore($team, null, true)
        );
    }



    /**
     * The submission's external id lands in a script block, and in shadow mode it comes from
     * the remote CCS without ever passing the validator.
     */
    public function testShowDiffEncodesTheSubmissionId(): void
    {
        $this->serializer
            ->method('serialize')
            ->willReturnCallback(fn(mixed $data): string => json_encode($data));

        $html = $this->twigExtension->showDiff('editor1', 'diff-main.c', "');alert(1);//", 'main.c', []);

        // json_encode also escapes the slashes, which is what keeps a </script> out of it.
        self::assertStringContainsString('const submissionId = "\');alert(1);\\/\\/";', $html);
        self::assertStringNotContainsString("const submissionId = '", $html);
    }

    /**
     * runDiff renders the output a contestant's program produced next to the reference output.
     */
    public function testRunDiffEscapesBothSides(): void
    {
        $html = $this->twigExtension->runDiff([
            'output_run'       => self::XSS_PAYLOAD,
            'output_reference' => 'expected',
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

}
