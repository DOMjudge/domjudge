<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Problem;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ImportProblemServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testEmptyYaml()
    {
        $yaml = '';
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $this->assertTrue($ret);
        $this->assertEquals('', $problem->getName());
    }

    public function testMinimalYamlTest()
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

    public function testTypesYamlTest()
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

    public function testUnknownProblemType()
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

    public function testInvalidProblemType() {
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

    public function testValidatorFlags()
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

    public function testCustomValidation()
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

    public function testMemoryLimit()
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

    public function testOutputLimit()
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

    public function testMultipassLimit()
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

    public function testMaximalProblem() {
        $yaml = <<<YAML
name: test
type: pass-fail
validation: custom multi-pass
validator_flags: 'special flags'
limits:
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
        $this->assertEquals(23*1024, $problem->getMemlimit());
        $this->assertEquals(42*1024, $problem->getOutputlimit());
        $this->assertEquals(3, $problem->getMultipassLimit());
        $this->assertEquals('special flags', $problem->getSpecialCompareArgs());
    }
}
