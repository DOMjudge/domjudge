<?php declare(strict_types=1);
/* These configuration settings are common to all parts of the
 * DOMjudge system and included by the specific configuration files.
 */

/** Loglevels and debugging */

// Log to syslog facility; do not define to disable.
define('SYSLOG', LOG_LOCAL0);

// Set DEBUG as a bitmask of the following settings.
// Of course never to be used on live systems!

define('DEBUG_PHP_NOTICE', 1); // Display PHP notice level warnings
define('DEBUG_SQL', 4); // Display SQL queries on webpages
define('DEBUG_JUDGE', 8); // Display judging scripts debug info

define('DEBUG', 0);

// By default report all PHP errors, except notices.
error_reporting(E_ALL & ~E_NOTICE);

// Set error reporting to all in debugging mode
if (DEBUG & DEBUG_PHP_NOTICE) {
    error_reporting(E_ALL);
}
