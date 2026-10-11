<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Utils\ImportFileParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ImportFileParserTest extends TestCase
{
    /**
     * @param string[] $lines
     * @param string[][] $expected
     */
    #[DataProvider('provideValidTsv')]
    public function testParseTsvValid(string $type, array $lines, array $expected): void
    {
        self::assertSame($expected, ImportFileParser::parseTsv($type, $lines, $message));
        self::assertNull($message);
    }

    public static function provideValidTsv(): Generator
    {
        $rows = [['team', 'Team 1', 'team001', 'secret']];
        foreach (['accounts', 'File_Version', 'ACCOUNTS'] as $header) {
            foreach (["\n", "\r\n"] as $eol) {
                foreach ([true, false] as $trailingEol) {
                    $line = "team\tTeam 1\tteam001\tsecret" . ($trailingEol ? $eol : '');
                    yield "$header, " . json_encode($eol) . ", trailing eol: " . json_encode($trailingEol) => [
                        'accounts', ["$header\t1$eol", $line], $rows,
                    ];
                }
            }
        }
        yield 'no rows' => ['groups', ["groups\t1\n"], []];
        yield 'several rows' => ['groups', ["groups\t1\n", "1\tA\n", "2\tB\n"], [['1', 'A'], ['2', 'B']]];
        yield 'optional trailing field' => [
            'accounts', ["accounts\t1\n", "team\tT\tt1\tpw\t1.2.3.4\n"], [['team', 'T', 't1', 'pw', '1.2.3.4']],
        ];
        yield 'escaped tab in field' => ['groups', ["groups\t1\n", "1\tA\\tB\n"], [['1', "A\tB"]]];
        yield 'teams accept version 2' => ['teams', ["teams\t2\n", "1\tTeam\n"], [['1', 'Team']]];
    }

    /**
     * @param string[] $lines
     */
    #[DataProvider('provideInvalidTsv')]
    public function testParseTsvInvalid(string $type, array $lines, string $expectedMessage): void
    {
        self::assertNull(ImportFileParser::parseTsv($type, $lines, $message));
        self::assertSame($expectedMessage, $message);
    }

    public static function provideInvalidTsv(): Generator
    {
        yield 'unknown version' => ['accounts', ["accounts\t2\n"], "TSV has unknown format or version: accounts\t2 != 1"];
        yield 'wrong type' => ['accounts', ["groups\t1\n"], "TSV has unknown format or version: groups\t1 != 1"];
        yield 'no header' => ['accounts', [], "TSV has unknown format or version:  != 1"];
        yield 'teams version 3' => ['teams', ["teams\t3\n"], "TSV has unknown format or version: teams\t3 != [12]"];
    }

    /**
     * @param array<int, mixed> $expected
     */
    #[DataProvider('provideValidJsonOrYaml')]
    public function testDecodeJsonOrYamlValid(string $content, array $expected): void
    {
        self::assertSame($expected, ImportFileParser::decodeJsonOrYaml($content, $message));
        self::assertNull($message);
    }

    public static function provideValidJsonOrYaml(): Generator
    {
        $data = [['id' => 'team001', 'username' => 'team001', 'name' => 'Team 1', 'password' => 'secret', 'type' => 'team']];

        yield 'json' => [json_encode($data), $data];
        yield 'json pretty' => [json_encode($data, JSON_PRETTY_PRINT), $data];
        yield 'yaml block' => [
            "- id: team001\n  username: team001\n  name: Team 1\n  password: secret\n  type: team\n",
            $data,
        ];
        yield 'yaml quoted keys' => [
            "- \"id\": team001\n  \"username\": team001\n  \"name\": Team 1\n  \"password\": secret\n  \"type\": team",
            $data,
        ];
        foreach ([[1, 0], [1, 3], [2, 2]] as [$inline, $indent]) {
            yield "yaml dump inline=$inline indent=$indent" => [Yaml::dump($data, $inline, $indent ?: 4), $data];
        }
        // Unquoted keys and values with tabs and irregular spacing are not valid
        // JSON but are valid YAML (flow style).
        yield 'yaml flow with tabs' => [
            "[{id: \tteam001,\tusername: team001, name:     Team 1,password: secret, type: team   }]",
            $data,
        ];
        yield 'yaml flow multi-line' => [
            "[{  id: team001,\n  username: team001,\n  name: Team 1,\n  password: secret,\n  type: team\n}]",
            $data,
        ];
    }

    #[DataProvider('provideInvalidJsonOrYaml')]
    public function testDecodeJsonOrYamlInvalid(string $content, string $expectedMessagePrefix): void
    {
        self::assertNull(ImportFileParser::decodeJsonOrYaml($content, $message));
        self::assertStringStartsWith($expectedMessagePrefix, $message);
    }

    public static function provideInvalidJsonOrYaml(): Generator
    {
        yield 'object instead of list' => ['{"id": "a"}', 'File contents does not contain JSON array'];
        yield 'empty list' => ['[]', 'File contents does not contain JSON array'];
        yield 'scalar' => ['"just a string"', 'File contents does not contain JSON array'];
        yield 'neither json nor yaml' => ["[{id: a\n", 'File contents is not valid JSON or YAML: '];
    }
}
