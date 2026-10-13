<?php declare(strict_types=1);

namespace App\Utils;

use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Decoding of the TSV, JSON and YAML files that can be imported.
 *
 * This only deals with the file format. What the decoded data means for
 * a specific import type is up to the import service.
 */
class ImportFileParser
{
    /**
     * Validate the header of a TSV file and split the remaining lines into fields.
     *
     * @param string[] $lines The lines of the file, including the header
     * @return string[][]|null One list of fields per line after the header,
     *                         or null if the header is invalid, in which case
     *                         $message is set
     */
    public static function parseTsv(string $type, array $lines, ?string &$message = null): ?array
    {
        // The first line of the tsv is always the format with a version number.
        // Currently, we hardcode version 1 because there are no others.
        $version = rtrim((string)array_shift($lines));
        // Two variants are in use: one where the first token is a static string
        // "File_Version" and the second where it's the type, e.g. "groups".
        $versionMatch = '1';
        if ($type == 'teams') {
            $versionMatch = '[12]';
        }
        $regex = sprintf("/^(File_Version|%s)\t%s$/i", $type, $versionMatch);
        if (!preg_match($regex, $version)) {
            $message = sprintf("TSV has unknown format or version: %s != %s", $version, $versionMatch);
            return null;
        }

        return array_map(fn(string $line) => Utils::parseTsvLine(trim($line)), $lines);
    }

    /**
     * Decode a JSON file, falling back to YAML, which is a superset of JSON.
     *
     * @return array<int, mixed>|null The decoded list, or null if the content
     *                                is not a list, in which case $message is set
     */
    public static function decodeJsonOrYaml(string $content, ?string &$message = null): ?array
    {
        try {
            $data = Utils::jsonDecode($content);
        } catch (JsonException) {
            // Check if we can parse it as YAML
            try {
                $data = Yaml::parse($content, Yaml::PARSE_DATETIME);
            } catch (ParseException $parseException) {
                $message = "File contents is not valid JSON or YAML: " . $parseException->getMessage();
                return null;
            }
        }

        if (!is_array($data) || !is_int(key($data))) {
            $message = 'File contents does not contain JSON array';
            return null;
        }

        return $data;
    }
}
