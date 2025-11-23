<?php declare(strict_types=1);
namespace App\Utils;

use DateTime;
use Doctrine\Inflector\InflectorFactory;
use enshrined\svgSanitize\Sanitizer as SvgSanitizer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Generic utility class.
 */
class Utils
{
    /** @var array<string, string> Mapping from HTML colors to hex values */
    final public const HTML_COLORS = [
        "black" => "#000000",
        "silver" => "#C0C0C0",
        "gray" => "#808080",
        "white" => "#FFFFFF",
        "maroon" => "#800000",
        "red" => "#FF0000",
        "purple" => "#800080",
        "fuchsia" => "#FF00FF",
        "green" => "#008000",
        "lime" => "#00FF00",
        "olive" => "#808000",
        "yellow" => "#FFFF00",
        "navy" => "#000080",
        "blue" => "#0000FF",
        "teal" => "#008080",
        "aqua" => "#00FFFF",
        "aliceblue" => "#f0f8ff",
        "antiquewhite" => "#faebd7",
        "aquamarine" => "#7fffd4",
        "azure" => "#f0ffff",
        "beige" => "#f5f5dc",
        "bisque" => "#ffe4c4",
        "blanchedalmond" => "#ffebcd",
        "blueviolet" => "#8a2be2",
        "brown" => "#a52a2a",
        "burlywood" => "#deb887",
        "cadetblue" => "#5f9ea0",
        "chartreuse" => "#7fff00",
        "chocolate" => "#d2691e",
        "coral" => "#ff7f50",
        "cornflowerblue" => "#6495ed",
        "cornsilk" => "#fff8dc",
        "crimson" => "#dc143c",
        "cyan" => "#00ffff",
        "darkblue" => "#00008b",
        "darkcyan" => "#008b8b",
        "darkgoldenrod" => "#b8860b",
        "darkgray" => "#a9a9a9",
        "darkgreen" => "#006400",
        "darkgrey" => "#a9a9a9",
        "darkkhaki" => "#bdb76b",
        "darkmagenta" => "#8b008b",
        "darkolivegreen" => "#556b2f",
        "darkorange" => "#ff8c00",
        "darkorchid" => "#9932cc",
        "darkred" => "#8b0000",
        "darksalmon" => "#e9967a",
        "darkseagreen" => "#8fbc8f",
        "darkslateblue" => "#483d8b",
        "darkslategray" => "#2f4f4f",
        "darkslategrey" => "#2f4f4f",
        "darkturquoise" => "#00ced1",
        "darkviolet" => "#9400d3",
        "deeppink" => "#ff1493",
        "deepskyblue" => "#00bfff",
        "dimgray" => "#696969",
        "dimgrey" => "#696969",
        "dodgerblue" => "#1e90ff",
        "firebrick" => "#b22222",
        "floralwhite" => "#fffaf0",
        "forestgreen" => "#228b22",
        "gainsboro" => "#dcdcdc",
        "ghostwhite" => "#f8f8ff",
        "gold" => "#ffd700",
        "goldenrod" => "#daa520",
        "greenyellow" => "#adff2f",
        "grey" => "#808080",
        "honeydew" => "#f0fff0",
        "hotpink" => "#ff69b4",
        "indianred" => "#cd5c5c",
        "indigo" => "#4b0082",
        "ivory" => "#fffff0",
        "khaki" => "#f0e68c",
        "lavender" => "#e6e6fa",
        "lavenderblush" => "#fff0f5",
        "lawngreen" => "#7cfc00",
        "lemonchiffon" => "#fffacd",
        "lightblue" => "#add8e6",
        "lightcoral" => "#f08080",
        "lightcyan" => "#e0ffff",
        "lightgoldenrodyellow" => "#fafad2",
        "lightgray" => "#d3d3d3",
        "lightgreen" => "#90ee90",
        "lightgrey" => "#d3d3d3",
        "lightpink" => "#ffb6c1",
        "lightsalmon" => "#ffa07a",
        "lightseagreen" => "#20b2aa",
        "lightskyblue" => "#87cefa",
        "lightslategray" => "#778899",
        "lightslategrey" => "#778899",
        "lightsteelblue" => "#b0c4de",
        "lightyellow" => "#ffffe0",
        "limegreen" => "#32cd32",
        "linen" => "#faf0e6",
        "magenta" => "#ff00ff",
        "mediumaquamarine" => "#66cdaa",
        "mediumblue" => "#0000cd",
        "mediumorchid" => "#ba55d3",
        "mediumpurple" => "#9370db",
        "mediumseagreen" => "#3cb371",
        "mediumslateblue" => "#7b68ee",
        "mediumspringgreen" => "#00fa9a",
        "mediumturquoise" => "#48d1cc",
        "mediumvioletred" => "#c71585",
        "midnightblue" => "#191970",
        "mintcream" => "#f5fffa",
        "mistyrose" => "#ffe4e1",
        "moccasin" => "#ffe4b5",
        "navajowhite" => "#ffdead",
        "oldlace" => "#fdf5e6",
        "olivedrab" => "#6b8e23",
        "orange" => "#ffa500",
        "orangered" => "#ff4500",
        "orchid" => "#da70d6",
        "palegoldenrod" => "#eee8aa",
        "palegreen" => "#98fb98",
        "paleturquoise" => "#afeeee",
        "palevioletred" => "#db7093",
        "papayawhip" => "#ffefd5",
        "peachpuff" => "#ffdab9",
        "peru" => "#cd853f",
        "pink" => "#ffc0cb",
        "plum" => "#dda0dd",
        "powderblue" => "#b0e0e6",
        "rosybrown" => "#bc8f8f",
        "royalblue" => "#4169e1",
        "saddlebrown" => "#8b4513",
        "salmon" => "#fa8072",
        "sandybrown" => "#f4a460",
        "seagreen" => "#2e8b57",
        "seashell" => "#fff5ee",
        "sienna" => "#a0522d",
        "skyblue" => "#87ceeb",
        "slateblue" => "#6a5acd",
        "slategray" => "#708090",
        "slategrey" => "#708090",
        "snow" => "#fffafa",
        "springgreen" => "#00ff7f",
        "steelblue" => "#4682b4",
        "tan" => "#d2b48c",
        "thistle" => "#d8bfd8",
        "tomato" => "#ff6347",
        "turquoise" => "#40e0d0",
        "violet" => "#ee82ee",
        "wheat" => "#f5deb3",
        "whitesmoke" => "#f5f5f5",
        "yellowgreen" => "#9acd32",
    ];

    final public const GD_MISSING = 'Cannot import image: the PHP GD library is missing.';

    final public const DAY_IN_SECONDS = 60*60*24;

    // Regex to parse relative times. Note that these are our own relative times, which allows
    // more than the CLICS spec does
    final public const RELTIME_REGEX = '/^([+-])?(\d+):(\d{2})(?::(\d{2})(?:\.(\d+))?)?$/';

    /**
     * Returns the milliseconds part of a time stamp truncated at three digits.
     */
    private static function getMillis(float $seconds): string
    {
        return sprintf(".%03d", floor(1000 * $seconds - 1000 * floor($seconds)));
    }

    /**
     * Prints the absolute time as yyyy-mm-ddThh:mm:ss(.uuu)?[+-]zz(:mm)?
     * (with millis if $floored is false).
     */
    public static function absTime(mixed $epoch, bool $floored = false): ?string
    {
        if ($epoch === null) {
            return null;
        }
        $millis = self::getMillis((float) $epoch);
        return date("Y-m-d\TH:i:s", (int) $epoch)
            . ($floored ? '' : $millis)
            . date("P", (int) $epoch);
    }

    public static function isRelTime(string $time): bool
    {
        return preg_match(self::RELTIME_REGEX, $time) === 1;
    }

    /**
     * Prints a time diff as relative time as ([-+])?(h)*h:mm:ss(.uuu)?
     * (with millis if $floored is false and with + sign only if $includePlus is true).
     */
    public static function relTime(float $seconds, bool $floored = false, bool $includePlus = false): string
    {
        $sign = ($seconds < 0) ? '-' : ($includePlus ? '+' : '');
        $seconds = abs($seconds);
        $hours = (int)($seconds / 3600);
        $minutes = (int)(($seconds - $hours*3600)/60);
        $millis = self::getMillis($seconds);
        $seconds = $seconds - $hours*3600 - $minutes*60;
        return $sign . sprintf("%d:%02d:%02d", $hours, $minutes, $seconds)
            . ($floored ? '' : $millis);
    }

    public static function relTimeToSeconds(string $reltime): float
    {
        preg_match(self::RELTIME_REGEX, $reltime, $data);
        $negative = ($data[1] === '-');
        $modifier = $negative ? -1 : 1;
        $seconds  = $modifier * (
                      (int)$data[2] * 3600
                    + (int)$data[3] * 60
                    + (float)sprintf('%d.%03d', $data[4] ?? 0, $data[5] ?? 0));
        return $seconds;
    }

    /**
     * Parse a string as time and return as epoch in float format (with
     * optional fractional part). The original time string should be in one of
     * the formats understood by DateTime (e.g. an ISO 8601 date and time with
     * fractional seconds). Throws an exception if $time cannot be parsed.
     */
    public static function toEpochFloat(string $time): float
    {
        $dt = new DateTime($time);
        return (float)sprintf('%d.%06d', $dt->getTimestamp(), $dt->format('u'));
    }

    /**
     * Returns epoch with microsecond resolution. Can be used to
     * simulate MySQL UNIX_TIMESTAMP() function to create insert
     * queries that do not change when replicated later.
     */
    public static function now(): float
    {
        return microtime(true);
    }

    /**
     * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
     * Returned value is time difference in seconds.
     */
    public static function difftime(float $time1, float $time2): float
    {
        return $time1 - $time2;
    }

    /**
     * Calculate the difference between two HH:MM:SS strings and output again in that format.
     * Assumes that $time1 >= $time2.
     */
    public static function timeStringDiff(string $time1, string $time2): string
    {
        // Add 00: to both times if they only contain one :. This might be the case if we have no hours
        if (count(explode(':', $time1)) == 2) {
            $time1 = '00:' . $time1;
        }
        if (count(explode(':', $time2)) == 2) {
            $time2 = '00:' . $time2;
        }

        sscanf($time1, '%2d:%2d:%2d', $h1, $m1, $s1);
        sscanf($time2, '%2d:%2d:%2d', $h2, $m2, $s2);

        $s = 3600 * ($h1 - $h2) + 60 * ($m1 - $m2) + ($s1 - $s2);

        $h = floor($s / (60 * 60));
        $s -= $h * 60 * 60;
        $m = floor($s / 60);
        $s -= $m * 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /**
     * Convert the given color to a hex value.
     */
    public static function convertToHex(string $color): ?string
    {
        if (preg_match('/^#[[:xdigit:]]{3}(?:[[:xdigit:]]{3}){0,2}$/', $color)) {
            return $color;
        }

        $color = strtolower(preg_replace('/[[:space:]]/', '', $color));
        if (isset(static::HTML_COLORS[$color])) {
            return strtoupper(static::HTML_COLORS[$color]);
        }
        return null;
    }

    /**
     * Convert the given hex color to the best matching string representation.
     */
    public static function convertToColor(string $hex): ?string
    {
        if (!preg_match('/^#[[:xdigit:]]{3,6}$/', $hex)) {
            return $hex;
        }

        // Expand short 3 digit hex version.
        if (preg_match('/^#[[:xdigit:]]{3}$/', $hex)) {
            $new = '#';
            for ($i = 1; $i <= 3; $i++) {
                $new .= str_repeat($hex[$i], 2);
            }
            $hex = $new;
        }
        if (!preg_match('/^#[[:xdigit:]]{6}$/', $hex)) {
            return null;
        }

        // Find the best match in L1 distance.
        $bestmatch = '';
        $bestdist  = 999999;

        foreach (static::HTML_COLORS as $color => $rgb) {
            $dist = 0;
            for ($i = 1; $i <= 3; $i++) {
                sscanf(substr($hex, 2 * $i - 1, 2), '%x', $val1);
                sscanf(substr($rgb, 2 * $i - 1, 2), '%x', $val2);
                $dist += abs($val1 - $val2);
            }
            if ($dist < $bestdist) {
                $bestdist  = $dist;
                $bestmatch = $color;
            }
        }

        return $bestmatch;
    }

    /**
     * Parse a hex color into it's three RGB values.
     *
     * @return array{int, int, int}
     */
    public static function parseHexColor(string $hex): array
    {
        // Source: https://stackoverflow.com/a/21966100
        $length = (strlen($hex) - 1) / 3;
        $fact = [17, 1, 0.062272][$length - 1];
        return [
            (int)round(hexdec(substr($hex, 1, $length)) * $fact),
            (int)round(hexdec(substr($hex, 1 + $length, $length)) * $fact),
            (int)round(hexdec(substr($hex, 1 + 2 * $length, $length)) * $fact)
        ];
    }

    /**
     * Convert an RGB component to its hex value.
     */
    public static function componentToHex(int $component): string
    {
        $hex = dechex($component);
        return strlen($hex) == 1 ? "0" . $hex : $hex;
    }

    /**
     * Convert an RGB triple into a CSS hex color.
     *
     * @param array{int, int, int} $color
     */
    public static function rgbToHex(array $color): string
    {
        $result = "#";
        for ($i=0; $i<count($color); $i++) {
            $result .= static::componentToHex($color[$i]);
        }
        return $result;
    }

    public static function relativeLuminance(string $rgb): float
    {
        // See https://en.wikipedia.org/wiki/Relative_luminance
        [$r, $g, $b] = static::parseHexColor($rgb);

        [$lr, $lg, $lb] = [
            pow($r / 255, 2.4),
            pow($g / 255, 2.4),
            pow($b / 255, 2.4),
        ];

        return 0.2126 * $lr + 0.7152 * $lg + 0.0722 * $lb;
    }

    public static function apcaContrast(string $fgColor, string $bgColor): float
    {
        // Based on WCAG 3.x (https://www.w3.org/TR/wcag-3.0/)
        $luminanceForeground = static::relativeLuminance($fgColor);
        $luminanceBackground = static::relativeLuminance($bgColor);

        $contrast = ($luminanceBackground > $luminanceForeground)
            ? (pow($luminanceBackground, 0.56) - pow($luminanceForeground, 0.57)) * 1.14
            : (pow($luminanceBackground, 0.65) - pow($luminanceForeground, 0.62)) * 1.14;

        return round($contrast * 100, 2);
    }

    /**
     * @return array{string, string}
     */
    public static function hexToForegroundAndBorder(string $rgb): array
    {
        $background = Utils::parseHexColor($rgb);

        // Pick a border that's a bit darker.
        // We explicit keep the alpha channel as-is.
        $darker = $background;
        $darker[0] = max($darker[0] - 64, 0);
        $darker[1] = max($darker[1] - 64, 0);
        $darker[2] = max($darker[2] - 64, 0);
        $border    = Utils::rgbToHex($darker);

        // Pick the text color with the biggest absolute contrast.
        $contrastWithWhite = static::apcaContrast('#ffffff', $rgb);
        $contrastWithBlack = static::apcaContrast('#000000', $rgb);

        $foreground = (abs($contrastWithBlack) > abs($contrastWithWhite)) ? '#000000' : '#ffffff';

        return [$foreground, $border];
    }

    /**
     * Return a rounded float.
     */
    public static function roundedFloat(?float $value = null, int $decimals = 3): ?float
    {
        if (is_null($value)) {
            return null;
        }

        // Truncate the string version to a specified number of decimals,
        // since PHP floats seem not very reliable in not giving e.g.
        // 1.9999 instead of 2.0.
        $decpos = strpos((string)$value, '.');
        if ($decpos === false) {
            return (float)$value;
        }
        return (float)substr((string)$value, 0, $decpos + $decimals + 1);
    }

    /**
     * Calculate the penalty time.
     *
     * @param bool $solved Whether there was at least one correct submission by this team for this problem
     * @param int $numSubmissions The total number of tries for this problem by this team
     * @param int $penaltyTime The penalty time for every wrong submission
     * @param bool $scoreIsInSeconds Whether scoring is in seconds
     */
    public static function calcPenaltyTime(bool $solved, int $numSubmissions, int $penaltyTime, bool $scoreIsInSeconds): int
    {
        if (!$solved) {
            return 0;
        }

        $result = ($numSubmissions - 1) * $penaltyTime;
        //  Convert the penalty time to seconds if the configuration
        //  parameter to compute scores to the second is set.
        if ($scoreIsInSeconds) {
            $result *= 60;
        }

        return $result;
    }

    /**
     * Get the time as used on the scoreboard (i.e. truncated minutes or seconds, depending on the scoreboard
     * resolution setting).
     */
    public static function scoretime(float|string $time, bool $scoreIsInSeconds): int
    {
        if ($scoreIsInSeconds) {
            $result = (int)($time);
        } else {
            $result = (int)floor($time / 60);
        }
        return $result;
    }

    /**
     * Formats a given hostname. If $full = true, then
     * the full hostname will be printed, else only
     * the local part (for keeping tables readable).
     */
    public static function printhost(string $hostname, bool $full = false): string
    {
        // Shorten the hostname to first label, but not if it's an IP address.
        if (! $full  && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
            $expl = explode('.', $hostname);
            $hostname = array_shift($expl);
        }

        return $hostname;
    }

    /**
     * Print (file) size in human-readable format by using B,KB,MB,GB suffixes.
     * Input is an integer (the size in bytes), output a string with suffix.
     */
    public static function printsize(int $size, int $decimals = 1): string
    {
        $factor = 1024;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $display = $size;

        $exact = true;
        for ($i = 0; $i < count($units) && $display >= $factor; $i++) {
            if (((int)$display % $factor)!=0) {
                $exact = false;
            }
            $display /= $factor;
        }

        if ($exact) {
            $decimals = 0;
        }
        return sprintf("%.{$decimals}lf %s", round($display, $decimals), $units[$i]);
    }

    /**
     * Print a time formatted as specified. The format is according to date().
     */
    public static function printtime(string|float|null $datetime, string $format): string
    {
        if (empty($datetime)) {
            return '';
        }
        return htmlspecialchars(date($format, (int)$datetime));
    }

    /**
     * Print the time something took from start to end (which defaults to now).
     *
     * Copied from lib/www/print.php
     */
    public static function printtimediff(?float $start, ?float $end = null): string
    {
        if (is_null($start)) {
            return '-';
        }
        if (is_null($end)) {
            $end = microtime(true);
        }
        $ret  = '';
        $diff = floor($end - $start);

        if ($diff >= 24 * 60 * 60) {
            $d    = floor($diff / (24 * 60 * 60));
            $ret  .= $d . "d ";
            $diff -= $d * 24 * 60 * 60;
        }
        if ($diff >= 60 * 60 || isset($d)) {
            $h    = floor($diff / (60 * 60));
            $ret  .= $h . ":";
            $diff -= $h * 60 * 60;
        }
        $m    = floor($diff / 60);
        $ret  .= sprintf('%02d:', $m);
        $diff -= $m * 60;
        $ret  .= sprintf('%02d', $diff);

        return $ret;
    }

    /**
     * Cut a string at $size chars and append ..., only if necessary.
     */
    public static function cutString(string $str, int $size): string
    {
        // is the string already short enough?
        // we count '…' for 1 extra chars.
        if (mb_strlen($str) <= $size+1) {
            return $str;
        }

        return mb_substr($str, 0, $size) . '…';
    }

    /**
     * Compute the LCS diff of two lines.
     *
     * @return array{bool, string}
     */
    public static function computeLcsDiff(string $line1, string $line2): array
    {
        $tokens1 = preg_split('/\s+/', $line1);
        $tokens2 = preg_split('/\s+/', $line2);
        $cutoff = 100; // a) LCS gets in-performant, b) the output is no longer readable.

        $n1 = min($cutoff, sizeof($tokens1));
        $n2 = min($cutoff, sizeof($tokens2));

        // compute longest common sequence length
        $dp = array_fill(0, $n1+1, array_fill(0, $n2+1, 0));
        for ($i = 1; $i < $n1 + 1; $i++) {
            for ($j = 1; $j < $n2 + 1; $j++) {
                if ($tokens1[$i-1] == $tokens2[$j-1]) {
                    $dp[$i][$j] = $dp[$i-1][$j-1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i-1][$j], $dp[$i][$j-1]);
                }
            }
        }

        if ($n1 == $n2 && $n1 == $dp[$n1][$n2]) {
            return [false, htmlspecialchars($line1) . "\n"];
        }

        // reconstruct lcs
        $i = $n1;
        $j = $n2;
        $lcs = [];
        while ($i > 0 && $j > 0) {
            if ($tokens1[$i-1] == $tokens2[$j-1]) {
                $lcs[] = $tokens1[$i-1];
                $i--;
                $j--;
            } elseif ($dp[$i-1][$j] > $dp[$i][$j-1]) {
                $i--;
            } else {
                $j--;
            }
        }
        $lcs = array_reverse($lcs);

        // Reconstruct diff.
        $diff = "";
        $l = sizeof($lcs);
        $i = 0;
        $j = 0;
        for ($k = 0; $k < $l; $k++) {
            while ($i < $n1 && $tokens1[$i] != $lcs[$k]) {
                $diff .= "<del>" . htmlspecialchars($tokens1[$i]) . "</del> ";
                $i++;
            }
            while ($j < $n2 && $tokens2[$j] != $lcs[$k]) {
                $diff .= "<ins>" . htmlspecialchars($tokens2[$j]) . "</ins> ";
                $j++;
            }
            $diff .= $lcs[$k] . " ";
            $i++;
            $j++;
        }
        while ($i < $n1 && ($k >= $l || $tokens1[$i] != $lcs[$k])) {
            $diff .= "<del>" . htmlspecialchars($tokens1[$i]) . "</del> ";
            $i++;
        }
        while ($j < $n2 && ($k >= $l || $tokens2[$j] != $lcs[$k])) {
            $diff .= "<ins>" . htmlspecialchars($tokens2[$j]) . "</ins> ";
            $j++;
        }

        if ($cutoff < sizeof($tokens1) || $cutoff < sizeof($tokens2)) {
            $diff .= "[cut off rest of line...]";
        }
        $diff .= "\n";

        return [true, $diff];
    }

    /**
     * Determine the image type for this image.
     */
    public static function getImageType(string $image, ?string &$error = null): bool|string
    {
        if (!function_exists('gd_info')) {
            $error = self::GD_MISSING;
            return false;
        }

        $info = getimagesizefromstring($image);
        if ($info === false) {
            $error = "Could not determine image information.";
            return false;
        }

        $type = image_type_to_extension($info[2], false);

        if (!in_array($type, ['jpeg', 'png', 'gif'])) {
            $error = "Unsupported image type '$type' found.";
            return false;
        }

        return $type;
    }

    /**
     * Generate resized thumbnail image and return as string.
     * Return FALSE on errors and stores error message in $error if set.
     */
    public static function getImageThumb(string $image, int $thumbMaxSize, string $tmpdir, ?string &$error = null): bool|string
    {
        if (!function_exists('gd_info')) {
            $error = self::GD_MISSING;
            return false;
        }

        $type = self::getImageType($image, $error);
        if ($type === false) {
            $error = "Could not determine image information.";
            return false;
        }

        $info = getimagesizefromstring($image);

        $rescale   = $thumbMaxSize / max($info[0], $info[1]);
        $thumbsize = [
            (int)max(round($info[0] * $rescale), 1),
            (int)max(round($info[1] * $rescale), 1),
        ];

        $orig  = imagecreatefromstring($image);
        $thumb = imagecreatetruecolor($thumbsize[0], $thumbsize[1]);
        if ($orig === false || $thumb === false) {
            $error = 'Cannot create GD image.';
            return false;
        }

        if (!imagecopyresampled($thumb, $orig, 0, 0, 0, 0,
                                $thumbsize[0], $thumbsize[1], $info[0], $info[1])) {
            $error = 'Cannot create resized thumbnail image.';
            return false;
        }

        if (!($tmpfname = tempnam($tmpdir, "thumb-"))) {
            $error = 'Cannot create temporary file in directory ' . $tmpdir . '.';
            return false;
        }

        $success = false;
        switch ($type) {
            case 'jpeg':
                $success = imagejpeg($thumb, $tmpfname);
                break;
            case 'png':
                $success = imagepng($thumb, $tmpfname);
                break;
            case 'gif':
                $success = imagegif($thumb, $tmpfname);
                break;
        }
        if (!$success) {
            $error = 'Failed to output thumbnail image.';
            return false;
        }
        if (($thumbstr = file_get_contents($tmpfname)) === false) {
            $error = 'Cannot read image from temporary file \'$tmpfname\'.';
            return false;
        }

        imagedestroy($orig);
        imagedestroy($thumb);

        return $thumbstr;
    }

    /**
     * Get the image size of the given image.
     *
     * This method supports PNG, JPG, BMP, GIF and SVG files.
     *
     * Returns an array with three items: the width, height and ratio between width and height.
     *
     * @return array{int, int, float}
     */
    public static function getImageSize(string $filename): array
    {
        if (mime_content_type($filename) === 'image/svg+xml') {
            $svg = simplexml_load_file($filename);
            if ($viewBox = $svg['viewBox']) {
                $viewBoxData = explode(' ', (string)$viewBox);
                $width = (int)$viewBoxData[2];
                $height = (int)$viewBoxData[3];
            } else {
                $width = (int)$svg['width'];
                $height = (int)$svg['height'];
            }
        } else {
            $size = @getimagesize($filename);
            $width = $size[0];
            $height = $size[1];
        }

        return [$width, $height, $width / $height];
    }

    public static function sanitizeSvg(string $svgContents): string | false
    {
        $sanitizer = new SvgSanitizer();
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);

        return $sanitizer->sanitize($svgContents);
    }

    /**
     * Returns TRUE iff string $haystack starts with string $needle.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
    }

    /**
     * Returns TRUE iff string $haystack ends with string $needle.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return mb_substr($haystack, mb_strlen($haystack)-mb_strlen($needle)) === $needle;
    }

    /**
     * Word wrap only unquoted text.
     */
    public static function wrapUnquoted(string $text, int $width = 75, string $quote = '>'): string
    {
        $lines = explode("\n", $text);

        $result = '';
        $unquoted = '';

        foreach ($lines as $line) {
            // Check for quoted lines
            if (strspn($line, $quote)>0) {
                // First append unquoted text wrapped, then quoted line:
                $result .= wordwrap($unquoted, $width);
                $unquoted = '';
                $result .= $line . "\n";
            } else {
                $unquoted .= $line . "\n";
            }
        }

        $result .= wordwrap(rtrim($unquoted), $width);

        return $result;
    }

    /**
     * Generate a random password.
     *
     * When $moreEntropy is `true`, generate a password of length 16 with
     * alphanumeric characters and _ and -. When `false`, generate a password of
     * length 6 with lowercase alphanumeric, except o, 0, l and 1. `false`
     * should be used when generating password that will be printed and handed
     * out. In other cases, use `true`.
     */
    public static function generatePassword(bool $moreEntropy = true): string
    {
        if ($moreEntropy) {
            $chars = array_merge(
                range('a', 'z'),
                range('A', 'Z'),
                range('0', '9'),
                ['-', '_']
            );
        } else {
            $chars = ['a','b','c','d','e','f','g','h','i','j','k','m','n','p','q','r',
                      's','t','u','v','w','x','y','z','2','3','4','5','6','7','8','9'];
        }

        $max_chars = count($chars) - 1;

        $rand_str = '';
        for ($i = 0; $i < ($moreEntropy ? 32 : 12); ++$i) {
            $rand_str .= $chars[random_int(0, $max_chars)];
        }

        return $rand_str;
    }

    /**
     * Convert size value as returned by ini_get to bytes.
     */
    public static function phpiniToBytes(string $size_str): int
    {
        return match (substr($size_str, -1)) {
            'M', 'm' => (int)$size_str * 1048576,
            'K', 'k' => (int)$size_str * 1024,
            'G', 'g' => (int)$size_str * 1073741824,
            default => (int)$size_str,
        };
    }

    /**
     * Return the table name for the given entity.
     */
    public static function tableForEntity(object $entity): string
    {
        $class        = $entity::class;
        $parts        = explode('\\', $class);
        $entityType   = $parts[count($parts) - 1];
        $inflector    = InflectorFactory::create()->build();
        return $inflector->tableize($entityType);
    }

    public static function streamAsBinaryFile(string $content, string $filename, string $type = 'octet-stream'): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($content) {
            echo $content;
        });
        $response->headers->set('Content-Type', 'application/' . $type);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', (string)strlen($content));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');
        return $response;
    }

    /** Note that as a side effect, $tempFilename will be deleted. */
    public static function streamZipFile(string $tempFilename, string $zipFilename): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($tempFilename) {
            $fp = fopen($tempFilename, 'rb');
            fpassthru($fp);
            unlink($tempFilename);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipFilename . '"');
        $response->headers->set('Content-Length', (string)filesize($tempFilename));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * Convert the given string to a field that is safe to use in a Tab Separated Values file.
     */
    public static function toTsvField(string $field) : string
    {
        return str_replace(
            ["\\",   "\t",  "\n",  "\r"],
            ["\\\\", "\\t", "\\n", "\\r"],
            $field
        );
    }

    /**
     * Split a line from a Tab Separated Values file into fields.
     *
     * @return string[]
     */
    public static function parseTsvLine(string $line): array
    {
        return array_map('stripcslashes', explode("\t", rtrim($line, "\r\n")));
    }

    /**
     * Reindex the given array by applying the callback to each item.
     *
     * @template TKey of array-key
     * @template UKey of array-key
     * @template V
     * @param array<TKey, V> $array
     * @param callable(V, TKey): UKey $callback
     * @return array<UKey, V>
     */
    public static function reindex(array $array, callable $callback): array
    {
        $reindexed = [];
        array_walk($array, function ($item, $key) use (&$reindexed, $callback) {
            $reindexed[$callback($item, $key)] = $item;
        });
        return $reindexed;
    }

    public static function getTextType(string $clientName, string $realPath): ?string
    {
        $textType = null;

        if (strrpos($clientName, '.') !== false) {
            $ext = substr($clientName, strrpos($clientName, '.') + 1);
            if (in_array($ext, ['txt', 'html', 'pdf'])) {
                $textType = $ext;
            }
        }
        if (!isset($textType)) {
            $finfo = finfo_open(FILEINFO_MIME);

            [$type] = explode('; ', finfo_file($finfo, $realPath));

            finfo_close($finfo);

            switch ($type) {
                case 'application/pdf':
                    $textType = 'pdf';
                    break;
                case 'text/html':
                    $textType = 'html';
                    break;
                case 'text/plain':
                    $textType = 'txt';
                    break;
            }
        }

        return $textType;
    }

    public static function getTextStreamedResponse(
        ?string $textType,
        BadRequestHttpException $exceptionMessage,
        string $filename,
        ?string $text
    ): StreamedResponse {
        $mimetype = match ($textType) {
            'pdf' => 'application/pdf',
            'html' => 'text/html',
            'txt' => 'text/plain',
            default => throw $exceptionMessage,
        };

        $response = new StreamedResponse();
        $response->setCallback(function () use ($text) {
            echo $text;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s"', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', (string)strlen($text));

        return $response;
    }

    /**
     * Decode a JSON string with our preferred settings.
     * @return mixed
     */
    public static function jsonDecode(string $str)
    {
        return json_decode($str, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Encode a JSON string with our preferred settings.
     */
    public static function jsonEncode(mixed $data): string
    {
        return json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function parseMetadata(string $raw_metadata): array
    {
        // TODO: Reduce duplication with judgedaemon code.
        $contents = explode("\n", $raw_metadata);
        $res = [];
        foreach ($contents as $line) {
            if (str_contains($line, ":")) {
                [$key, $value] = explode(":", $line, 2);
                $res[$key] = trim($value);
            }
        }

        return $res;
    }

    public static function extendMaxExecutionTime(int $minimumMaxExecutionTime): void
    {
        $maxExecutionTime = (int)ini_get('max_execution_time');
        if ($maxExecutionTime !== 0 && $maxExecutionTime < $minimumMaxExecutionTime) {
            ini_set('max_execution_time', $minimumMaxExecutionTime);
        }
    }

    /**
     * Call ob_flush() unless the top-level output buffer does not allow it.
     */
    public static function ob_flush_if_possible(): bool // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $status = ob_get_status();
        if (empty($status) || ($status['flags'] & PHP_OUTPUT_HANDLER_CLEANABLE)) {
            return ob_flush();
        }
        return false;
    }
}
