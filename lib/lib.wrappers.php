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
 * Try to decode a JSON string with our preferred settings.
 * Does not throw error, but errors can be obtained via json_last_error().
 */
function dj_json_try_decode(string $str)
{
    return json_decode($str, true);
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
    if (!isset($arg) || $arg==='') {
        return "''";
    }
    return escapeshellarg($arg);
}

/**
 * A simple function that allows to sleep for fractional seconds. Note that
 * usleep is documented to consume CPU cycles and may not work for times
 * larger than a second.
 * Returns a boolean value for success or if the delay was interrupted by a
 * signal, returns a float with the time remaining, similar to time_nanosleep.
 */
function dj_sleep(float $seconds)
{
    $second_in_nanoseconds = 1_000_000_000;

    $seconds_int = (int)$seconds;
    $nanoseconds = (int)($second_in_nanoseconds * ($seconds-$seconds_int));

    $result = time_nanosleep($seconds_int, $nanoseconds);
    if (is_array($result)) {
        $result = $result['seconds'] + $result['nanoseconds'] / $second_in_nanoseconds;
    }

    return $result;
}
