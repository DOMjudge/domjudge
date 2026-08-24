<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\ProblemSpec;

use App\Entity\Problem;
use App\Service\ImportProblemService;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Yaml\Yaml;

class ImportProblemSpecDraftTest extends ImportProblemSpecBaseTestCase
{
    protected array $problemSpecVersion = ['draft', '2025-09-draft', '2025-09'];
    protected const string PROBLEMSPEC_VERSION = 'draft';

    #[DataProvider('provideAlternativeTypeNotations')]
    public function testTypesStringWithAlternativeChars(string $separator): void
    {
        // This is heavily inspired by `ImportProblemSpecBaseTestCase::testTypesYamlTest`,
        // check that file when updating this test.
        $expectedTypes = ['pass-fail', 'interactive'];
        $typesAsString = implode($separator, $expectedTypes);
        $specVersion = $this->problemSpecVersion[0];
        $yaml = <<<YAML
name: test
problem_format_version: $specVersion
type: $typesAsString
YAML;

        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $messageString = var_export($messages, true);
        $this->assertTrue($ret, 'Parsing failed for type: ' . $typesAsString . ', messages: ' . $messageString);
        $problemTypes = $problem->getTypesAsStringArray();
        $this->assertEquals(
            $expectedTypes, $problemTypes,
            'Found: "' . implode(' ', $problemTypes) . '" vs Expected: "' . implode(' ', $expectedTypes) . '"'
        );
    }

    /**
     * @param string[] $expectedTypes
     */
    #[DataProvider('provideAlternativeArrayNotations')]
    public function testTypesSequenceStrings(string $yaml, array $expectedTypes): void
    {
        // This is heavily inspired by `ImportProblemSpecBaseTestCase::testTypesYamlTest`,
        // check that file when updating this test.
        $messages = [];
        $validationMode = 'xxx';
        $problem = new Problem();
        $typesAsString = implode(', ', $expectedTypes);

        $ret = ImportProblemService::parseYaml($yaml, $messages, $validationMode, PropertyAccess::createPropertyAccessor(), $problem);
        $messageString = var_export($messages, true);
        $this->assertTrue($ret, 'Parsing failed for type: ' . $typesAsString . ', messages: ' . $messageString);
        $problemTypes = $problem->getTypesAsStringArray();
        $this->assertEquals(
            $expectedTypes, $problemTypes,
            'Found: "' . implode(' ', $problemTypes) . '" vs Expected: "' . implode(' ', $expectedTypes) . '"'
        );
    }

    public static function provideAlternativeTypeNotations(): Generator
    {
        yield ["\t"];
        yield [", "];
        yield ["; "];
    }

    public static function provideAlternativeArrayNotations(): Generator
    {
        $expectedTypes = ['pass-fail', 'interactive', 'multi-pass'];
        $specVersion = static::PROBLEMSPEC_VERSION;
        $yamlBasic = <<<YAML
name: test
problem_format_version: $specVersion
YAML;
        $simpleArray = <<<YAML
$yamlBasic
type:
  - pass-fail
YAML;
        $mappedArray = <<<YAML
$yamlBasic
type:
  pass-fail: pass-fail
YAML;
        $oneLineArray = <<<YAML
$yamlBasic
type: [pass-fail]
YAML;
        $malformedArray = <<<YAML
$yamlBasic
type: [pass-fail, [multi-pass, interactive]]
YAML;
        $combinedStringArray = <<<YAML
$yamlBasic
type:
 - pass-fail
 - multi-pass, interactive
YAML;

        foreach([$simpleArray, $mappedArray, $oneLineArray] as $yamlFile) {
            yield [$yamlFile, ['pass-fail']];
        }
        yield [$malformedArray, ['pass-fail', 'multi-pass', 'interactive']];
        yield [$combinedStringArray, ['pass-fail', 'multi-pass', 'interactive']];
    }
}
