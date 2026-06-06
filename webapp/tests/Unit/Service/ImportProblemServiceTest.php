<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemStatementContent;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PropertyAccess\PropertyAccess;
use ZipArchive;

class ImportProblemServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testEmptyYaml(): void
    {
        $yaml = '';
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEquals('Unknown name', $problem->getName());
    }

    public function testMinimalYamlTest(): void
    {
        $yaml = <<<YAML
name: test
unknown_key: "doesn't break anything"
# no explicit type
# no explicit validation
# no explicit limits
# no validator flags
YAML;

        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('test', $problem->getName());
        $this->assertEquals('pass-fail', $problem->getTypesAsString());
        $this->assertEquals('default', $validationMode);
        $this->assertEquals(0, $problem->getTimelimit());
        $this->assertEquals(null, $problem->getMemlimit());
        $this->assertEquals(null, $problem->getOutputlimit());
        $this->assertEquals(null, $problem->getSpecialCompareArgs());
    }

    public function testTypesYamlTest(): void
    {
        foreach ([
                     'pass-fail',
                     'scoring',
                     'multi-pass',
                     'interactive',
                     'submit-answer',
                     'pass-fail multi-pass',
                     'pass-fail interactive',
                     'pass-fail submit-answer',
                     'scoring multi-pass',
                     'scoring interactive',
                     'scoring submit-answer',
                 ] as $type) {
            $yaml = <<<YAML
name: test
type: $type
YAML;

            $messages = [];
            $validationMode = 'xxx';
            $problem = new Problem();

            $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
            $messageString = var_export($messages, true);
            $this->assertTrue($ret, 'Parsing failed for type: ' . $type . ', messages: ' . $messageString);
            if (in_array($type, ['interactive', 'multi-pass', 'submit-answer'])) {
                // Default to pass-fail if not explicitly set.
                $type = 'pass-fail ' . $type;
            }
            $typesString = str_replace(' ', ', ', $type);
            $this->assertEquals($typesString, $problem->getTypesAsString());
        }
    }

    public function testUnknownProblemType(): void
    {
        $yaml = <<<YAML
name: test
type: invalid-type
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertFalse($ret);
        $messagesString = var_export($messages, true);
        $this->assertStringContainsString('Unknown problem type', $messagesString);
    }

    public function testInvalidProblemType(): void
    {
        foreach ([
                     'pass-fail scoring',
                     'submit-answer multi-pass',
                     'submit-answer interactive',
                 ] as $type) {
            $yaml = <<<YAML
name: test
type: $type
YAML;
            $messages = [];
            $validationMode = 'xxx';
            $problem = new Problem();

            $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
            $this->assertFalse($ret);
            $messagesString = var_export($messages, true);
            $this->assertStringContainsString('Invalid problem type', $messagesString);
        }
    }

    public function testValidatorFlags(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
validator_flags: 'float_tolerance 1E-6'
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('float_tolerance 1E-6', $problem->getSpecialCompareArgs());
    }

    public function testValidatorArgs(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
validator_args: 'float_tolerance 1E-7'
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('float_tolerance 1E-7', $problem->getSpecialCompareArgs());
    }

    public function testValidatorFlagsAndArgs(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
validator_flags: 'float_tolerance 1E-8'
validator_args: 'float_tolerance 1E-9'
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('float_tolerance 1E-8', $problem->getSpecialCompareArgs());
    }

    public function testCustomValidation(): void
    {
        foreach (['custom', 'custom interactive', 'custom multi-pass'] as $mode) {
            $yaml = <<<YAML
name: test
validation: $mode
YAML;
            $messages = [];
            $validationMode = 'xxx';
            $problem = new Problem();

            $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
            $this->assertTrue($ret);
            $this->assertEmpty($messages);
            $this->assertEquals($mode, $validationMode);
            if ($mode === 'custom multi-pass') {
                $this->assertEquals('pass-fail, multi-pass', $problem->getTypesAsString());
                $this->assertEquals(2, $problem->getMultipassLimit());
            } elseif ($mode === 'custom interactive') {
                $this->assertEquals('pass-fail, interactive', $problem->getTypesAsString());
            } else {
                $this->assertEquals('pass-fail', $problem->getTypesAsString());
            }
        }
    }

    public function testTimeLimit(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
limits:
  time_limit: 5
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals(5, $problem->getTimelimit());
    }

    public function testTimeLimitFloat(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
limits:
  time_limit: 2.5
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals(2.5, $problem->getTimelimit());
    }

    public function testTimeLimitNegativeRejected(): void
    {
        $yaml = <<<YAML
name: test
limits:
  time_limit: -5
YAML;
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
        ]);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $result = $service->importZippedProblem($zip, 'test-problem.zip', null, null, $messages);

        $zip->close();
        unlink($zipFile);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('timelimit: This value should be greater than 0', $messages['danger'][0]);
    }

    public function testTimeLimitWithUnitsRejected(): void
    {
        $yaml = <<<YAML
name: test
limits:
  time_limit: 5m
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertFalse($ret);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('timelimit: Expected argument of type "float", "string" given', $messages['danger'][0]);
    }

    public function testMemoryLimit(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
limits:
  memory: 1234
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals(1234*1024, $problem->getMemlimit());
    }

    public function testOutputLimit(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
limits:
  output: 4223
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals(4223*1024, $problem->getOutputlimit());
    }

    public function testMultipassLimit(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail multi-pass
limits:
  validation_passes: 7
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals(7, $problem->getMultipassLimit());
    }

    /**
     * @return array<string, string[]>
     */
    public static function provideInvalidValidationPasses(): array
    {
        return [
            'one pass' => ['1'],
            'zero passes' => ['0'],
            'negative' => ['-3'],
            'not an integer' => ['2.5'],
            'not a number' => ["'many'"],
        ];
    }

    #[DataProvider('provideInvalidValidationPasses')]
    public function testInvalidMultipassLimit(string $validationPasses): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail multi-pass
limits:
  validation_passes: $validationPasses
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertFalse($ret);
        $this->assertCount(1, $messages['danger']);
        $this->assertStringContainsString('validation_passes must be an integer >= 2', $messages['danger'][0]);
        // parseYaml applies all collected properties only once it succeeds, so
        // the problem is left untouched, i.e. not even multi-pass.
        $this->assertFalse($problem->isMultipassProblem());
    }

    public function testMaximalProblem(): void
    {
        $yaml = <<<YAML
name: test
type: pass-fail
validation: custom multi-pass
validator_flags: 'special flags'
limits:
  time_limit: 7
  memory: 23
  output: 42
  validation_passes: 3
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('pass-fail, multi-pass', $problem->getTypesAsString());
        $this->assertEquals('custom multi-pass', $validationMode);
        $this->assertEquals(7, $problem->getTimelimit());
        $this->assertEquals(23*1024, $problem->getMemlimit());
        $this->assertEquals(42*1024, $problem->getOutputlimit());
        $this->assertEquals(3, $problem->getMultipassLimit());
        $this->assertEquals('special flags', $problem->getSpecialCompareArgs());
    }

    public function testMultipleLanguages(): void
    {
        $yaml = <<<YAML
name:
    de: deutsch
    en: english
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('english', $problem->getName());
    }

    public function testKattisExample(): void
    {
        $yaml = <<<YAML
problem_format_version: 2023-07-draft
uuid: 5ca6ba5b-36d5-4eff-8aa7-d967cbc4375e
source: Kattis
license: cc by-sa

type: interactive
name:
  en: Guess the Number
  sv: Gissa talet

# Override standard limits: say that the TLE solutions provided should
# be at least 4 times above the time limit in order for us to be
# happy.
limits:
  time_multipliers:
    time_limit_to_tle: 4
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $this->assertEquals('Guess the Number', $problem->getName());
        $this->assertEquals('pass-fail, interactive', $problem->getTypesAsString());
        $this->assertEquals('custom interactive', $validationMode);
        $this->assertEquals(0, $problem->getTimelimit());
        $this->assertEquals(null, $problem->getMemlimit());
        $this->assertEquals(null, $problem->getOutputlimit());
    }

    public function testParseTestCaseGroupMetaValidAcceptScore(): void
    {
        $yaml = "accept_score: 50";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNotNull($result);
        $this->assertEmpty($messages['danger'] ?? []);
        $this->assertEquals('50.000000000', $result->getAcceptScore());
    }

    public function testParseTestCaseGroupMetaNegativeAcceptScoreRejected(): void
    {
        $yaml = "accept_score: -10";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('must not be negative', $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaNonNumericAcceptScoreRejected(): void
    {
        $yaml = "accept_score: abc";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString("Invalid accept_score 'abc'", $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaValidRange(): void
    {
        $yaml = "range: 0 100";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNotNull($result);
        $this->assertEmpty($messages['danger'] ?? []);
        $this->assertEquals('0.000000000', $result->getRangeLowerBound());
        $this->assertEquals('100.000000000', $result->getRangeUpperBound());
    }

    public function testParseTestCaseGroupMetaNegativeRangeLowerBoundRejected(): void
    {
        $yaml = "range: -10 100";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('bounds must not be negative', $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaNegativeRangeUpperBoundRejected(): void
    {
        $yaml = "range: 0 -50";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('bounds must not be negative', $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaRangeLowerExceedsUpperRejected(): void
    {
        $yaml = "range: 100 50";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('lower bound must not exceed upper bound', $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaInvalidRangeFormatRejected(): void
    {
        $yaml = "range: 100";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString("Invalid range '100'", $messages['danger'][0]);
    }

    public function testParseTestCaseGroupMetaProblemSpecSpecifiedOutputValidatorFlagsAccepted(): void
    {
        $yaml = "output_validator_flags: --any arg -x should work";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNotNull($result);
        $danger_messages = $messages['danger'] ?? [];
        $this->assertEmpty($danger_messages, "Failed with: " . implode(', ', $danger_messages));
        $this->assertEquals('--any arg -x should work', $result->getOutputValidatorFlags());
    }

    public function testParseTestCaseGroupMetaUnspecifiedOutputValidatorArgsAccepted(): void
    {
        $yaml = "output_validator_args: --any arg -x should work";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNotNull($result);
        $danger_messages = $messages['danger'] ?? [];
        $this->assertEmpty($danger_messages, "Failed with: " . implode(', ', $danger_messages));
        $this->assertEquals('--any arg -x should work', $result->getOutputValidatorFlags());
    }

    public function testParseTestCaseGroupMetaProblemSpecSpecifiedOutputValidatorFlagsAndArgsSpecified(): void
    {
        $yaml = <<<YAML
output_validator_flags: --any arg -x should work
output_validator_args: --those args -must not work
YAML;
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNotNull($result);
        $danger_messages = $messages['danger'] ?? [];
        $this->assertEmpty($danger_messages, "Failed with: " . implode(', ', $danger_messages));
        $this->assertEquals('--any arg -x should work', $result->getOutputValidatorFlags());
    }


    /**
     * Create a temporary zip file with the given contents.
     *
     * @param array<string, string> $files Map of filename => content
     */
    private function createZipWithContents(array $files): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'domjudge-test-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $tempFile;
    }

    public function testTimelimitFileWithUnitsRejected(): void
    {
        $yaml = <<<YAML
name: test
YAML;
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
            '.timelimit' => '1s',
        ]);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $result = $service->importZippedProblem($zip, 'test-problem.zip', null, null, $messages);

        $zip->close();
        unlink($zipFile);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('Invalid time limit', $messages['danger'][0]);
        $this->assertStringContainsString('1s', $messages['danger'][0]);
    }

    public function testTimelimitFileZeroRejected(): void
    {
        $yaml = <<<YAML
name: test
YAML;
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
            '.timelimit' => '0',
        ]);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $result = $service->importZippedProblem($zip, 'test-problem.zip', null, null, $messages);

        $zip->close();
        unlink($zipFile);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('timelimit: This value should be greater than 0', $messages['danger'][0]);
    }

    public function testTimelimitConflictDetected(): void
    {
        $yaml = <<<YAML
name: test
limits:
  time_limit: 5
YAML;
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
            '.timelimit' => '10',
        ]);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $result = $service->importZippedProblem($zip, 'test-problem.zip', null, null, $messages);

        $zip->close();
        unlink($zipFile);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString('Conflicting time limits', $messages['danger'][0]);
        $this->assertStringContainsString('10', $messages['danger'][0]);
        $this->assertStringContainsString('5', $messages['danger'][0]);
    }

    public function testTimelimitNoConflictWhenMatching(): void
    {
        $yaml = <<<YAML
name: test
limits:
  time_limit: 5
YAML;
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
            '.timelimit' => '5',
        ]);

        $zip = new ZipArchive();
        $zip->open($zipFile);

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $result = $service->importZippedProblem($zip, 'test-problem.zip', null, null, $messages);

        $zip->close();
        unlink($zipFile);

        // Should succeed (no conflict)
        $this->assertInstanceOf(Problem::class, $result);
        $this->assertEmpty($messages['danger']);
        $this->assertEquals(5, $result->getTimelimit());
    }

    public function testProblemStatementReplacementOnReimport(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);

        $problemId = 'statement-import';
        $problem = (new Problem())
            ->setExternalid($problemId)
            ->setName('Statement import')
            ->setTimelimit(1);
        $statement = (new ProblemStatementContent())
            ->setProblem($problem)
            ->setContent("old statement\n");
        $problem
            ->setProblemStatementContent($statement)
            ->setProblemstatementType('txt');
        $em->persist($problem);
        $em->flush();

        $yaml = <<<YAML
name: Statement import
limits:
  time_limit: 1
YAML;
        $newContent = "new statement from zip\n";
        $zipFile = $this->createZipWithContents([
            'problem.yaml' => $yaml,
            'problem.txt' => $newContent,
        ]);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipFile));
        /** @var array{info: string[], warning: string[], danger: string[]} $messages */
        $messages = ['info' => [], 'warning' => [], 'danger' => []];
        try {
            $result = $service->importZippedProblem($zip, "$problemId.zip", $problem, null, $messages);
        } finally {
            $zip->close();
            @unlink($zipFile);
        }
        self::assertInstanceOf(Problem::class, $result);
        self::assertEmpty($messages['danger']);

        $em->clear();
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        self::assertInstanceOf(Problem::class, $problem);
        self::assertSame($newContent, $problem->getProblemstatement());
        self::assertSame('txt', $problem->getProblemstatementType());
        self::assertCount(1, $em->getRepository(ProblemStatementContent::class)->findBy(['problem' => $problem]));

        // Re-importing without a statement keeps the existing reset semantics.
        $zipFile = $this->createZipWithContents(['problem.yaml' => $yaml]);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipFile));
        $messages = ['info' => [], 'warning' => [], 'danger' => []];
        try {
            $result = $service->importZippedProblem($zip, "$problemId.zip", $problem, null, $messages);
        } finally {
            $zip->close();
            @unlink($zipFile);
        }
        self::assertInstanceOf(Problem::class, $result);
        self::assertEmpty($messages['danger']);

        $em->clear();
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        self::assertInstanceOf(Problem::class, $problem);
        self::assertNull($problem->getProblemStatementContent());
        self::assertNull($problem->getProblemstatementType());
        self::assertCount(0, $em->getRepository(ProblemStatementContent::class)->findBy(['problem' => $problem]));
    }

    public function testParseTestCaseGroupMetaInvalidRangeTooManyValuesRejected(): void
    {
        $yaml = "range: 100 101 102";
        $messages = [];

        $result = ImportProblemService::parseTestCaseGroupMeta($yaml, 'test-group', $messages);

        $this->assertNull($result);
        $this->assertNotEmpty($messages['danger']);
        $this->assertStringContainsString("Invalid range '100 101 102'", $messages['danger'][0]);
    }

    #[DataProvider('provideLanguages')]
    public function testLanguages(
        array $expectedLanguages, ?array $secondUploadExpectedLanguages = null,
        ?string $languagesString = null, ?string $secondUploadLanguagesString = null
    ): void {
        $problem = new Problem();
        for ($i=0; $i<2; $i++) {
            if ($i === 1) {
                if ($secondUploadExpectedLanguages !== null) {
                    $expectedLanguages = $secondUploadExpectedLanguages;
                    $languagesString = $secondUploadLanguagesString;
                }
            }
            if ($languagesString === null) {
                $languagesString = implode(', ', $expectedLanguages);
            }
            $yaml = <<<YAML
name: restricted languages
languages: $languagesString
YAML;
            $messages = [];
            $validationMode = 'xxx';

            /** @var ImportProblemService $service */
            $service = static::getContainer()->get(ImportProblemService::class);

            $ret = ImportProblemService::parseYaml(
                $yaml, $messages, $validationMode,
                PropertyAccess::createPropertyAccessor(), $problem,
                fn(array $languageIds, array &$failedLanguageIds): array => $service->helperLanguages($languageIds, $failedLanguageIds),
            );
            $this->assertTrue($ret);
            $this->assertEmpty($messages);
            $problemLanguages = [];
            foreach ($problem->getLanguages() as $language) {
                $problemLanguages[] = $language->getExternalid();
            }
            sort($problemLanguages);
            sort($expectedLanguages);
            $this->assertEquals($expectedLanguages, $problemLanguages);
        }
    }

    /**
     * @param string[] $unknownLanguages
     */
    #[DataProvider('provideUnknownLanguages')]
    public function testUnknownLanguageSpecified(array $unknownLanguages, ?string $unknownYamlLanguages = null): void{
        if (!$unknownYamlLanguages) {
            $unknownYamlLanguages = 'languages: ' . implode(', ', $unknownLanguages);
        }
        $yaml = <<<YAML
name: restricted languages
$unknownYamlLanguages
YAML;
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);

        $ret = ImportProblemService::parseYaml(
            $yaml, $messages, $validationMode,
            PropertyAccess::createPropertyAccessor(), $problem,
            fn(array $languageIds, array &$failedLanguageIds): array => $service->helperLanguages($languageIds, $failedLanguageIds),
        );
        $this->assertFalse($ret);
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString("Unknown language(s): '" . implode("', '", $unknownLanguages) . "'.", $messages['danger'][0]);
    }

    /**
     * @param string[] $expectedLanguages
     */
    #[DataProvider('provideLanguagesAsArray')]
    public function testLanguagesSpecifiedAsArray(string $yaml, array $expectedLanguages): void{
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        /** @var ImportProblemService $service */
        $service = static::getContainer()->get(ImportProblemService::class);

        $ret = ImportProblemService::parseYaml(
            $yaml, $messages, $validationMode,
            PropertyAccess::createPropertyAccessor(), $problem,
            fn(array $languageIds, array &$failedLanguageIds): array => $service->helperLanguages($languageIds, $failedLanguageIds),
        );

        $this->assertTrue($ret);
        $this->assertEmpty($messages);
        $problemLanguages = [];
        foreach ($problem->getLanguages() as $language) {
            $problemLanguages[] = $language->getExternalid();
        }
        sort($problemLanguages);
        sort($expectedLanguages);
        $this->assertEquals($expectedLanguages, $problemLanguages);
    }

    public static function provideLanguages(): Generator
    {
        // Initial languages, second upload languages, special languageString, second special language string
        yield [['ada']];
        yield [['awk', 'r', 'sh']];
        yield [['bash', 'c', 'cpp', 'csharp']];
        yield [['haskell', 'java', 'javascript', 'kotlin', 'lua']];
        yield [['ocaml', 'pascal', 'prolog', 'python3', 'ruby', 'rust']];
        yield [['scala', 'swift']];
        yield [[], null, 'all']; // This checks the implementation detail
        //// See: https://www.kattis.com/problem-package-format/appendix/languages.html
        //// (fortran => f95, pl => perl) in our setup.
        //// yield [['fortran', 'perl']]
        yield [['ada', 'r'], ['ada']];
        yield [['ada'], ['ada', 'r']];
        yield [['ada'], ['r']];
        yield [[], ['ada'], 'all'];
        yield [['ada'], [], null, 'all'];
    }

    public static function provideUnknownLanguages(): Generator
    {
        yield [['DOMtower']];
        yield [['Huttese', 'Klingon']];
        $yamlLanguages = <<<YAML
languages: yes, true, false, 0, 1, null, 2.5
YAML;
        yield [['0', '1', '2.5', 'false', 'null', 'true', 'yes'], $yamlLanguages];
        $yamlLanguages = <<<YAML
languages:
- yes
- no
- true
- false
- null
- 0
- 1
- 10
- 1.5
YAML;
        yield [['0', '1', '1.5', '10', 'false', 'no', 'null', 'true', 'yes'], $yamlLanguages];
        $yamlLanguages = <<<YAML
languages: [null, yes, no, true, false, 0, -1, 20]
YAML;
        yield [['-1', '0', '20', 'false', 'no', 'null', 'true', 'yes'], $yamlLanguages];
        $yamlLanguages = <<<YAML
languages: true
YAML;
        yield [['true'], $yamlLanguages];
        $yamlLanguages = <<<YAML
languages: 1.5
YAML;
        yield [['1.5'], $yamlLanguages];
        $yamlLanguages = <<<YAML
languages: null
YAML;
        yield [['null'], $yamlLanguages];
    }

    public static function provideLanguagesAsArray(): Generator
    {
        $yaml1 = <<<YAML
name: restricted languages
languages:
  - ada
YAML;
        $yaml2 = <<<YAML
name: restricted languages
languages: [ada]
YAML;
        $yaml3 = <<<YAML
name: restricted languages
languages: ["ada"]
YAML;
        foreach ([$yaml1, $yaml2, $yaml3] as $yaml) {
            yield [$yaml, ['ada']];
        }
        $yaml1 = <<<YAML
name: restricted languages
languages:
  - ada
  - bash
YAML;
        $yaml2 = <<<YAML
name: restricted languages
languages: [bash, ada]
YAML;
        $yaml3 = <<<YAML
name: restricted languages
languages: ["ada", bash]
YAML;
        $yaml4 = <<<YAML
name: restricted languages
languages:
 ada: ada
 bash: bash
YAML;
        foreach ([$yaml1, $yaml2, $yaml3, $yaml4] as $yaml) {
            yield [$yaml, ['ada', 'bash']];
        }
    }
}
