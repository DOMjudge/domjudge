<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Problem;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
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
}
