<?php
/* These configuration settings are common to all parts of the
 * DOMjudge system and included by the specific configuration files.
 */

/** Loglevels and debugging */

// Log to syslog facility; do not define to disable.
define('SYSLOG', LOG_LOCAL0);

// Set DEBUG as a bitmask of the following settings.
// Of course never to be used on live systems!

define('DEBUG_PHP_NOTICE', 1); // Display PHP notice level warnings
define('DEBUG_TIMINGS', 2); // Display timings for loading webpages
define('DEBUG_SQL', 4); // Display SQL queries on webpages
define('DEBUG_JUDGE', 8); // Display judging scripts debug info

define('DEBUG', 0);

// By default report all PHP errors, except notices.
error_reporting(E_ALL & ~E_NOTICE);

// Set error reporting to all in debugging mode
if (DEBUG & DEBUG_PHP_NOTICE) {
    error_reporting(E_ALL);
}

// Mapping of DOMjudge verdict strings to those defined in the CLICS
// CCS specification (and a few more common ones) at:
// https://clics.ecs.baylor.edu/index.php/Contest_Control_System#Judge_Responses
$VERDICTS = array(
    'compiler-error'     => 'CE',
    'memory-limit'       => 'MLE',
    'output-limit'       => 'OLE',
    'run-error'          => 'RTE',
    'timelimit'          => 'TLE',
    'wrong-answer'       => 'WA',
    'presentation-error' => 'PE', /* dropped since 5.0 */
    'no-output'          => 'NO',
    'correct'            => 'AC',
);
