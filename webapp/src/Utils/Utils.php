<?php declare(strict_types=1);
namespace App\Utils;

use App\Entity\SubmissionFile;
use DateTime;

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

    /** @var array Mapping from ISO3166-1 alpha-3 to country names */
    const ALPHA3_COUNTRIES = [
        "AFG" => "Afghanistan",
        // Removed since not an "official" country
        // "ALA" => "Åland Islands",
        "ALB" => "Albania",
        "DZA" => "Algeria",
        "ASM" => "American Samoa",
        "AND" => "Andorra",
        "AGO" => "Angola",
        "AIA" => "Anguilla",
        "ATA" => "Antarctica",
        "ATG" => "Antigua and Barbuda",
        "ARG" => "Argentina",
        "ARM" => "Armenia",
        "ABW" => "Aruba",
        "AUS" => "Australia",
        "AUT" => "Austria",
        "AZE" => "Azerbaijan",
        "BHS" => "Bahamas",
        "BHR" => "Bahrain",
        "BGD" => "Bangladesh",
        "BRB" => "Barbados",
        "BLR" => "Belarus",
        "BEL" => "Belgium",
        "BLZ" => "Belize",
        "BEN" => "Benin",
        "BMU" => "Bermuda",
        "BTN" => "Bhutan",
        "BOL" => "Bolivia",
        // Removed since not an "official" country
        // "BES" => "Bonaire, Sint Eustatius and Saba",
        "BIH" => "Bosnia and Herzegovina",
        "BWA" => "Botswana",
        "BVT" => "Bouvet Island",
        "BRA" => "Brazil",
        "IOT" => "British Indian Ocean Territory",
        "BRN" => "Brunei Darussalam",
        "BGR" => "Bulgaria",
        "BFA" => "Burkina Faso",
        "BDI" => "Burundi",
        "CPV" => "Cabo Verde",
        "KHM" => "Cambodia",
        "CMR" => "Cameroon",
        "CAN" => "Canada",
        "CYM" => "Cayman Islands",
        "CAF" => "Central African Republic",
        "TCD" => "Chad",
        "CHL" => "Chile",
        "CHN" => "China",
        "CXR" => "Christmas Island",
        "CCK" => "Cocos (Keeling) Islands",
        "COL" => "Colombia",
        "COM" => "Comoros",
        "COG" => "Congo",
        "COD" => "Congo (Democratic Republic of the)",
        "COK" => "Cook Islands",
        "CRI" => "Costa Rica",
        "CIV" => "Côte d'Ivoire",
        "HRV" => "Croatia",
        "CUB" => "Cuba",
        "CUW" => "Curaçao",
        "CYP" => "Cyprus",
        "CZE" => "Czechia",
        "DNK" => "Denmark",
        "DJI" => "Djibouti",
        "DMA" => "Dominica",
        "DOM" => "Dominican Republic",
        "ECU" => "Ecuador",
        "EGY" => "Egypt",
        "SLV" => "El Salvador",
        "GNQ" => "Equatorial Guinea",
        "ERI" => "Eritrea",
        "EST" => "Estonia",
        "ETH" => "Ethiopia",
        "SWZ" => "Eswatini",
        "FLK" => "Falkland Islands",
        "FRO" => "Faroe Islands",
        "FJI" => "Fiji",
        "FIN" => "Finland",
        "FRA" => "France",
        "GUF" => "French Guiana",
        "PYF" => "French Polynesia",
        "ATF" => "French Southern Territories",
        "GAB" => "Gabon",
        "GMB" => "Gambia",
        "GEO" => "Georgia",
        "DEU" => "Germany",
        "GHA" => "Ghana",
        "GIB" => "Gibraltar",
        "GRC" => "Greece",
        "GRL" => "Greenland",
        "GRD" => "Grenada",
        "GLP" => "Guadeloupe",
        "GUM" => "Guam",
        "GTM" => "Guatemala",
        "GGY" => "Guernsey",
        "GIN" => "Guinea",
        "GNB" => "Guinea-Bissau",
        "GUY" => "Guyana",
        "HTI" => "Haiti",
        "HMD" => "Heard and McDonald Islands",
        "VAT" => "Holy See",
        "HND" => "Honduras",
        "HKG" => "Hong Kong",
        "HUN" => "Hungary",
        "ISL" => "Iceland",
        "IND" => "India",
        "IDN" => "Indonesia",
        "IRN" => "Iran",
        "IRQ" => "Iraq",
        "IRL" => "Ireland",
        "IMN" => "Isle of Man",
        "ISR" => "Israel",
        "ITA" => "Italy",
        "JAM" => "Jamaica",
        "JPN" => "Japan",
        "JEY" => "Jersey",
        "JOR" => "Jordan",
        "KAZ" => "Kazakhstan",
        "KEN" => "Kenya",
        "KIR" => "Kiribati",
        "PRK" => "North Korea",
        "KOR" => "South Korea",
        "KWT" => "Kuwait",
        "KGZ" => "Kyrgyzstan",
        "LAO" => "Laos",
        "LVA" => "Latvia",
        "LBN" => "Lebanon",
        "LSO" => "Lesotho",
        "LBR" => "Liberia",
        "LBY" => "Libya",
        "LIE" => "Liechtenstein",
        "LTU" => "Lithuania",
        "LUX" => "Luxembourg",
        "MAC" => "Macao",
        "MKD" => "North Macedonia",
        "MDG" => "Madagascar",
        "MWI" => "Malawi",
        "MYS" => "Malaysia",
        "MDV" => "Maldives",
        "MLI" => "Mali",
        "MLT" => "Malta",
        "MHL" => "Marshall Islands",
        "MTQ" => "Martinique",
        "MRT" => "Mauritania",
        "MUS" => "Mauritius",
        "MYT" => "Mayotte",
        "MEX" => "Mexico",
        "FSM" => "Micronesia",
        "MDA" => "Moldova",
        "MCO" => "Monaco",
        "MNG" => "Mongolia",
        "MNE" => "Montenegro",
        "MSR" => "Montserrat",
        "MAR" => "Morocco",
        "MOZ" => "Mozambique",
        "MMR" => "Myanmar",
        "NAM" => "Namibia",
        "NRU" => "Nauru",
        "NPL" => "Nepal",
        "NLD" => "Netherlands",
        "NCL" => "New Caledonia",
        "NZL" => "New Zealand",
        "NIC" => "Nicaragua",
        "NER" => "Niger",
        "NGA" => "Nigeria",
        "NIU" => "Niue",
        "NFK" => "Norfolk Island",
        "MNP" => "Northern Mariana Islands",
        "NOR" => "Norway",
        "OMN" => "Oman",
        "PAK" => "Pakistan",
        "PLW" => "Palau",
        "PSE" => "Palestine, State of",
        "PAN" => "Panama",
        "PNG" => "Papua New Guinea",
        "PRY" => "Paraguay",
        "PER" => "Peru",
        "PHL" => "Philippines",
        "PCN" => "Pitcairn",
        "POL" => "Poland",
        "PRT" => "Portugal",
        "PRI" => "Puerto Rico",
        "QAT" => "Qatar",
        "REU" => "Réunion",
        "ROU" => "Romania",
        "RUS" => "Russian Federation",
        "RWA" => "Rwanda",
        "BLM" => "Saint Barthélemy",
        "SHN" => "Saint Helena, Ascension and Tristan da Cunha",
        "KNA" => "Saint Kitts and Nevis",
        "LCA" => "Saint Lucia",
        "MAF" => "Saint Martin",
        "SPM" => "Saint Pierre and Miquelon",
        "VCT" => "Saint Vincent and the Grenadines",
        "WSM" => "Samoa",
        "SMR" => "San Marino",
        "STP" => "Sao Tome and Principe",
        "SAU" => "Saudi Arabia",
        "SEN" => "Senegal",
        "SRB" => "Serbia",
        "SYC" => "Seychelles",
        "SLE" => "Sierra Leone",
        "SGP" => "Singapore",
        "SXM" => "Sint Maarten",
        "SVK" => "Slovakia",
        "SVN" => "Slovenia",
        "SLB" => "Solomon Islands",
        "SOM" => "Somalia",
        "ZAF" => "South Africa",
        "SGS" => "South Georgia and the South Sandwich Islands",
        "SSD" => "South Sudan",
        "ESP" => "Spain",
        "LKA" => "Sri Lanka",
        "SDN" => "Sudan",
        "SUR" => "Suriname",
        "SJM" => "Svalbard and Jan Mayen",
        "SWE" => "Sweden",
        "CHE" => "Switzerland",
        "SYR" => "Syrian Arab Republic",
        "TWN" => "Taiwan",
        "TJK" => "Tajikistan",
        "TZA" => "Tanzania",
        "THA" => "Thailand",
        "TLS" => "Timor-Leste",
        "TGO" => "Togo",
        "TKL" => "Tokelau",
        "TON" => "Tonga",
        "TTO" => "Trinidad and Tobago",
        "TUN" => "Tunisia",
        "TUR" => "Turkey",
        "TKM" => "Turkmenistan",
        "TCA" => "Turks and Caicos Islands",
        "TUV" => "Tuvalu",
        "UGA" => "Uganda",
        "UKR" => "Ukraine",
        "ARE" => "United Arab Emirates",
        "GBR" => "United Kingdom",
        "USA" => "United States of America",
        "UMI" => "United States Minor Outlying Islands",
        "URY" => "Uruguay",
        "UZB" => "Uzbekistan",
        "VUT" => "Vanuatu",
        "VEN" => "Venezuela",
        "VNM" => "Viet Nam",
        "VGB" => "Virgin Islands (British)",
        "VIR" => "Virgin Islands (U.S.)",
        "WLF" => "Wallis and Futuna",
        "ESH" => "Western Sahara",
        "YEM" => "Yemen",
        "ZMB" => "Zambia",
        "ZWE" => "Zimbabwe",
    ];

    // returns the milliseconds part of a time stamp truncated at three digits
    private static function getMillis(float $seconds) : string
    {
        return sprintf(".%03d", floor(1000 * $seconds - 1000 * floor($seconds)));
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

    // Parse a string as time and return as epoch in float format (with
    // optional fractional part). The original time string should be in one of
    // the formats understood by DateTime (e.g. an ISO 8601 date and time with
    // fractional seconds). Throws an exception if $time cannot be parsed.
    public static function to_epoch_float(string $time) : float
    {
        $dt = new DateTime($time);
        return (float)sprintf('%d.%06d', $dt->getTimestamp(), $dt->format('u'));
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
     * Calculate the difference between two HH:MM:SS strings and output again in that format.
     * Assumes that $time1 >= $time2.
     * @param string $time1
     * @param string $time2
     * @return string
     */
    public static function timeStringDiff(string $time1, string $time2)
    {
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
     * @param bool $scoreIsInSeconds Whether scoring is in seconds
     * @return int
     */
    public static function calcPenaltyTime(bool $solved, int $numSubmissions, int $penaltyTime, bool $scoreIsInSeconds)
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

    /**
     * Formats a given hostname. If $full = true, then
     * the full hostname will be printed, else only
     * the local part (for keeping tables readable)
     */
    public static function printhost(string $hostname, bool $full = false) : string
    {
        // Shorten the hostname to first label, but not if it's an IP address.
        if (! $full  && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
            $expl = explode('.', $hostname);
            $hostname = array_shift($expl);
        }

        return $hostname;
    }

    /**
     * Print (file) size in human readable format by using B,KB,MB,GB suffixes.
     * Input is a integer (the size in bytes), output a string with suffix.
     */
    public static function printsize(int $size, int $decimals = 1) : string
    {
        $factor = 1024;
        $units = array('B', 'KB', 'MB', 'GB');
        $display = (int)$size;

        $exact = true;
        for ($i = 0; $i < count($units) && $display > $factor; $i++) {
            if (($display % $factor)!=0) {
                $exact = false;
            }
            $display /= $factor;
        }

        if ($exact) {
            $decimals = 0;
        }
        return sprintf("%.${decimals}lf %s", round($display, $decimals), $units[$i]);
    }

    /**
     * Print a time formatted as specified. The format is according to strftime().
     * @param string|float $datetime
     * @param string       $format
     * @return string
     */
    public static function printtime($datetime, string $format): string
    {
        if (empty($datetime)) {
            return '';
        }
        return Utils::specialchars(strftime($format, (int)floor($datetime)));
    }

    /**
     * Print the time something took from start to end (which defaults to now).
     *
     * Copied from lib/www/print.php
     * @param float $start
     * @param null  $end
     * @return string
     */
    public static function printtimediff(float $start, $end = null): string
    {
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
     * Wrapper around PHP's htmlspecialchars() to set desired options globally:
     *
     * - ENT_QUOTES: Also convert single quotes, in case string is contained
     *   in a single quoted context.
     * - ENT_HTML5: Display those single quotes as the HTML5 entity &apos;.
     * - ENT_SUBSTITUTE: Replace any invalid Unicode characters with the
     *   Unicode replacement character.
     *
     * Additionally, set the character set explicitly to the DOMjudge global
     * character set.
     * @param string $string
     * @return string
     */
    public static function specialchars(string $string) : string
    {
        return htmlspecialchars(
            $string,
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE
        );
    }

    /**
     * Cut a string at $size chars and append ..., only if neccessary.
     * @param string $str
     * @param int    $size
     * @return string
     */
    public static function cutString(string $str, int $size) : string
    {
        // is the string already short enough?
        // we count '…' for 1 extra chars.
        if (mb_strlen($str) <= $size+1) {
            return $str;
        }

        return mb_substr($str, 0, $size) . '…';
    }

    /**
     * Compute the LCS diff of two lines
     * @param string $line1
     * @param string $line2
     * @return array
     */
    public static function computeLcsDiff(string $line1, string $line2): array
    {
        $tokens1 = preg_split('/\s+/', $line1);
        $tokens2 = preg_split('/\s+/', $line2);
        $cutoff = 100; // a) LCS gets inperformant, b) the output is not longer readable

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
            return [false, Utils::specialchars($line1) . "\n"];
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

        // reconstruct diff
        $diff = "";
        $l = sizeof($lcs);
        $i = 0;
        $j = 0;
        for ($k = 0; $k < $l ; $k++) {
            while ($i < $n1 && $tokens1[$i] != $lcs[$k]) {
                $diff .= "<del>" . Utils::specialchars($tokens1[$i]) . "</del> ";
                $i++;
            }
            while ($j < $n2 && $tokens2[$j] != $lcs[$k]) {
                $diff .= "<ins>" . Utils::specialchars($tokens2[$j]) . "</ins> ";
                $j++;
            }
            $diff .= $lcs[$k] . " ";
            $i++;
            $j++;
        }
        while ($i < $n1 && ($k >= $l || $tokens1[$i] != $lcs[$k])) {
            $diff .= "<del>" . Utils::specialchars($tokens1[$i]) . "</del> ";
            $i++;
        }
        while ($j < $n2 && ($k >= $l || $tokens2[$j] != $lcs[$k])) {
            $diff .= "<ins>" . Utils::specialchars($tokens2[$j]) . "</ins> ";
            $j++;
        }

        if ($cutoff < sizeof($tokens1) || $cutoff < sizeof($tokens2)) {
            $diff .= "[cut off rest of line...]";
        }
        $diff .= "\n";

        return [true, $diff];
    }

    /**
     * Call `diff` on the two given files
     *
     * FIXME: this assumes GNU diff.
     *
     * @param $oldfile
     * @param $newfile
     * @return string
     */
    public static function systemDiff(string $oldfile, string $newfile): string
    {
        $oldname = basename($oldfile);
        $newname = basename($newfile);
        return (string)`diff -Bdt --strip-trailing-cr -U2 \
                 --label $oldname --label $newname $oldfile $newfile 2>&1`;
    }

    /**
     * Create a diff for the given two sources
     * @param SubmissionFile $newSource
     * @param string         $newFile
     * @param SubmissionFile $oldSource
     * @param string         $oldFile
     * @param string         $tmpdir
     * @return string
     */
    public static function createDiff(
        SubmissionFile $newSource,
        string $newFile,
        SubmissionFile $oldSource,
        string $oldFile,
        string $tmpdir
    ): string {
        // Try different ways of diffing, in order of preference.
        if (function_exists('xdiff_string_diff')) {
            // The PECL xdiff PHP-extension.
            $difftext = xdiff_string_diff($oldSource->getSourcecode(), $newSource->getSourcecode(), 2, true);
        } else {
            if (is_readable($oldFile) && is_readable($newFile)) {
                // A direct diff on the sources in the SUBMITDIR.
                $difftext = Utils::systemDiff($oldFile, $newFile);
            } else {
                // Try generating temporary files for executing diff.
                $oldFile = tempnam($tmpdir, sprintf("source-old-s%s-", $oldSource->getSubmitid()));
                $newFile = tempnam($tmpdir, sprintf("source-new-s%s-", $newSource->getSubmitid()));

                if (!$oldFile || !$newFile) {
                    $difftext = "DOMjudge: error generating temporary files for diff.";
                } else {
                    $oldhandle = fopen($oldFile, 'w');
                    $newhandle = fopen($newFile, 'w');

                    if (!$oldhandle || !$newhandle) {
                        $difftext = "DOMjudge: error opening temporary files for diff.";
                    } else {
                        if ((fwrite($oldhandle, $oldSource->getSourcecode()) === false) ||
                            (fwrite($newhandle, $newSource->getSourcecode()) === false)) {
                            $difftext = "DOMjudge: error writing temporary files for diff.";
                        } else {
                            $difftext = Utils::systemDiff($oldFile, $newFile);
                        }
                    }
                    if ($oldhandle) {
                        fclose($oldhandle);
                    }
                    if ($newhandle) {
                        fclose($newhandle);
                    }
                }

                if ($oldFile) {
                    unlink($oldFile);
                }
                if ($newFile) {
                    unlink($newFile);
                }
            }
        }

        return $difftext;
    }

    public static function balloonSym(string $color) : string
    {
        return sprintf('<i style="color: %s" class="fas fa-golf-ball"></i>', self::specialchars($color));
    }

    /**
     * Determine the image type for this image
     * @param string $image
     * @param string $error
     * @return bool|string
     */
    public static function getImageType(string $image, &$error)
    {
        if (!function_exists('gd_info')) {
            $error = "Cannot import image: the PHP GD library is missing.";
            return false;
        }

        $info = getimagesizefromstring($image);
        if ($info === false) {
            $error = "Could not determine image information.";
            return false;
        }

        $type = image_type_to_extension($info[2], false);

        if (!in_array($type, array('jpeg', 'png', 'gif'))) {
            $error = "Unsupported image type '$type' found.";
            return false;
        }

        return $type;
    }

    /**
     * Generate resized thumbnail image and return as as string.
     * Return FALSE on errors and stores error message in $error if set.
     * @param string $image
     * @param        $thumbMaxSize
     * @param        $tmpdir
     * @param string $error
     * @return bool|false|string
     */
    public static function getImageThumb(string $image, $thumbMaxSize, $tmpdir, &$error)
    {
        if (!function_exists('gd_info')) {
            $error = "Cannot import image: the PHP GD library is missing.";
            return false;
        }

        $type = self::getImageType($image, $error);
        if ($type === false) {
            $error = "Could not determine image information.";
            return false;
        }

        $info = getimagesizefromstring($image);

        $rescale   = $thumbMaxSize / max($info[0], $info[1]);
        $thumbsize = array(
            (int)round($info[0] * $rescale),
            (int)round($info[1] * $rescale)
        );

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
     * Returns TRUE iff string $haystack starts with string $needle
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle) : bool
    {
        return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
    }

    /**
     * Returns TRUE iff string $haystack ends with string $needle
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle) : bool
    {
        return mb_substr($haystack, mb_strlen($haystack)-mb_strlen($needle)) === $needle;
    }

    /**
     * Word wrap only unquoted text.
     */
    public static function wrap_unquoted(string $text, int $width = 75, string $quote = '>') : string
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
     * Generate a random password of length 6 with lowercase alphanumeric
     * characters, except o, 0, l and 1 since these can be confusing.
     */
    public static function generatePassword()
    {
        $chars = ['a','b','c','d','e','f','g','h','i','j','k','m','n','p','q','r',
                  's','t','u','v','w','x','y','z','2','3','4','5','6','7','8','9'];

        $max_chars = count($chars) - 1;

        $rand_str = '';
        for ($i = 0; $i < 6; ++$i) {
            $rand_str .= $chars[random_int(0, $max_chars)];
        }

        return $rand_str;
    }

    /**
     * Convert size value as returned by ini_get to bytes.
     */
    public static function phpini_to_bytes(string $size_str)
    {
        switch (substr($size_str, -1)) {
            case 'M': case 'm': return (int)$size_str * 1048576;
            case 'K': case 'k': return (int)$size_str * 1024;
            case 'G': case 'g': return (int)$size_str * 1073741824;
            default: return $size_str;
        }
    }
}
