<?php declare(strict_types=1);
namespace DOMJudgeBundle\Utils;

/**
 * Generic utility class.
 */
class Utils
{
    /** @var array Mapping from HTML colors to hex values */
    const HTML_COLORS = [
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

    // returns the milliseconds part of a time stamp truncated at three digits
    private static function getMillis(float $seconds) : string
    {
        return sprintf(".%03d", floor(1000*($seconds - floor($seconds))));
    }

    // prints the absolute time as yyyy-mm-ddThh:mm:ss(.uuu)?[+-]zz(:mm)?
    // (with millis if $floored is false)
    public static function absTime($epoch, bool $floored = false) : string
    {
        if ($epoch===null) {
            return null;
        }
        $millis = Utils::getMillis((float) $epoch);
        return date("Y-m-d\TH:i:s", (int) $epoch)
            . ($floored ? '' : $millis)
            . date("P", (int) $epoch);
    }

    // prints a time diff as relative time as (-)?(h)*h:mm:ss(.uuu)?
    // (with millis if $floored is false)
    public static function relTime(float $seconds, bool $floored = false) : string
    {
        $sign = ($seconds < 0) ? '-' : '';
        $seconds = abs($seconds);
        $hours = (int)($seconds / 3600);
        $minutes = (int)(($seconds - $hours*3600)/60);
        $millis = Utils::getMillis($seconds);
        $seconds = $seconds - $hours*3600 - $minutes*60;
        return $sign . sprintf("%d:%02d:%02d", $hours, $minutes, $seconds)
            . ($floored ? '' : $millis);
    }

    /**
     * Returns epoch with microsecond resolution. Can be used to
     * simulate MySQL UNIX_TIMESTAMP() function to create insert
     * queries that do not change when replicated later.
     */
    public static function now() : float
    {
        return microtime(true);
    }

    /**
     * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
     * Returned value is time difference in seconds.
     */
    public static function difftime(float $time1, float $time2) : float
    {
        return $time1 - $time2;
    }

    /**
     * Convert the given color to a hex value
     * @param string $color
     * @return string|null
     */
    public static function convertToHex(string $color)
    {
        if (preg_match('/^#[[:xdigit:]]{3,6}$/', $color)) {
            return $color;
        }

        $color = strtolower(preg_replace('/[[:space:]]/', '', $color));
        if (isset(static::HTML_COLORS[$color])) {
            return strtoupper(static::HTML_COLORS[$color]);
        }
        return null;
    }

    /**
     * Conver the given hex color to the best matching string representation
     * @param string $hex
     * @return string|null
     */
    public static function convertToColor(string $hex)
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
     * Return a rounded float
     * @param float|null $value
     * @param int $decimals
     * @return float|null
     */
    public static function roundedFloat(float $value = null, int $decimals = 3)
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
     * @param bool $scoreIsInSecods Whether scoring is in seconds
     * @return int
     */
    public static function calcPenaltyTime(bool $solved, int $numSubmissions, int $penaltyTime, bool $scoreIsInSecods)
    {
        if (!$solved) {
            return 0;
        }

        $result = ($numSubmissions - 1) * $penaltyTime;
        //  Convert the penalty time to seconds if the configuration
        //  parameter to compute scores to the second is set.
        if ($scoreIsInSecods) {
            $result *= 60;
        }

        return $result;
    }

    /**
     * Get the time as used on the scoreboard (i.e. truncated minutes or seconds, depending on the scoreboard resolution setting).
     * @param float|string $time
     * @param bool $scoreIsInSeconds
     * @return int
     */
    public static function scoretime($time, bool $scoreIsInSeconds)
    {
        if ($scoreIsInSeconds) {
            $result = (int)floor($time);
        } else {
            $result = (int)floor($time / 60);
        }
        return $result;
    }
}
