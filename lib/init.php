<?php declare(strict_types=1);
if (!defined('LIBDIR')) {
    die("LIBDIR not defined.");
}

require_once(LIBDIR . '/lib.error.php');
require_once(LIBDIR . '/lib.misc.php');
require_once(LIBDIR . '/lib.dbconfig.php');
require_once(LIBDIR . '/use_db.php');

// Raise floating point print precision (default is 14) to be able to
// use microsecond resolution timestamps. Note that since PHP uses the
// IEEE 754 double precision format, which can only handle about 15-16
// digits, we can't go beyond microseconds without reverting to
// arbitrary precision float formats. We set it to exactly 16, since
// that's the highest IEEE double supports anyways and higher seems to
// give spurious output with json_encode().
$precision = ini_get('precision');
if ($precision===false || empty($precision)) {
    error("Could not read PHP setting 'precision'");
}
ini_set('precision', '16');

// Set for using mb_* functions:
mb_internal_encoding(DJ_CHARACTER_SET);
