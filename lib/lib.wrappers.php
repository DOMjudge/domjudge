<?php declare(strict_types=1);
/**
 * Miscellaneous wrappers for PHP functions, included from lib.misc.php.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Decode a JSON string with our preferred settings.
 */
function dj_json_decode(string $str)
{
    return json_decode($str, true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Encode data to JSON with our preferred settings.
 */
function dj_json_encode($data) : string
{
    return json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/**
 * Helperfunction to read all contents from a file.
 * If $maxsize is set to a nonnegative integer, then limit data read
 * to this many bytes, and when the truncating the file, attach a note
 * saying so.
 */
function dj_file_get_contents(string $filename, int $maxsize = -1) : string
{
    if (! file_exists($filename)) {
        error("File does not exist: $filename");
    }
    if (! is_readable($filename)) {
        error("File is not readable: $filename");
    }

    if ($maxsize >= 0 && filesize($filename) > $maxsize) {
        $res = file_get_contents($filename, false, null, 0, $maxsize);
        if ($res===false) {
            error("Error reading from file: $filename");
        }
        $res .= "\n[output storage truncated after $maxsize B]\n";
    } else {
        $res = file_get_contents($filename);
        if ($res===false) {
            error("Error reading from file: $filename");
        }
    }

    return $res;
}

/**
 * Fix broken behaviour of escapeshellarg that it doesn't return '' for an
 * empty string.
 */
function dj_escapeshellarg(?string $arg) : string
{
    if (!isset($arg) || $arg==='') return "''";
    return escapeshellarg($arg);
}
