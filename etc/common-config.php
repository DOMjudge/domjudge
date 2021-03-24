<?php declare(strict_types=1);
/* These logging and debugging settings primarily influence the Judgedaemon.
 */

/** Loglevels and debugging */

// Log to syslog facility; do not define to disable.
define('SYSLOG', LOG_LOCAL0);


// Display PHP notice level warnings.
define('DEBUG_PHP_NOTICE', (1 << 0));
// Display judging scripts debug info, and enable the symfony profiler for
// requests from the judgedaemon.
define('DEBUG_JUDGE',      (1 << 3));

// Set DEBUG as a bitmask of the above settings, for example use
// DEBUG_PHP_NOTICE | DEBUG_JUDGE
// if you want to see both php notices *and* increase debug information for
// judgedaemons.
// Of course never to be used on live systems!
define('DEBUG', DEBUG_PHP_NOTICE);

// By default report all PHP errors, except notices.
error_reporting(E_ALL & ~E_NOTICE);

// Set error reporting to all in debugging mode
if (DEBUG & DEBUG_PHP_NOTICE) {
    error_reporting(E_ALL);
}
