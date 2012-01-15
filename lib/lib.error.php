<?php
/**
 * Error handling functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( ! defined('SCRIPT_ID') ) {
	define('SCRIPT_ID', basename($_SERVER['PHP_SELF'], '.php'));
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

// Is this the webinterface or commandline?
define('IS_WEB', isset($_SERVER['REMOTE_ADDR']));

// Open standard error:
if ( ! IS_WEB && ! defined('STDERR') ) {
	define('STDERR', fopen('php://stderr', 'w'));
}

// Open log file:
if ( defined('LOGFILE') ) {
	if ( $fp = @fopen(LOGFILE, 'a') ) {
		define('STDLOG', $fp);
	} else {
		fwrite(STDERR, "Cannot open log file " . LOGFILE .
			" for appending; continuing without logging.\n");
	}
	unset($fp);
}

// Open syslog connection:
if ( defined('SYSLOG') ) {
	openlog(SCRIPT_ID, LOG_NDELAY | LOG_PID, SYSLOG);
}

/**
 * Log a message $string on the loglevel $msglevel.
 * Prepends a timestamp and log to the logfile.
 * If this is the web interface: write to the screen with the right CSS class.
 * If this is the command line: write to Standard Error.
 */
function logmsg($msglevel, $string) {
	global $verbose, $loglevel;

	$stamp = "[" . strftime("%b %d %H:%M:%S") . "] " . SCRIPT_ID .
		(function_exists('posix_getpid') ? "[" . posix_getpid() . "]" : "") .
		": ";

	if ( $msglevel <= $verbose  ) {
		// if this is the webinterface, print it to stdout, else to stderr
		if ( IS_WEB ) {
			$msg = htmlspecialchars($string);
			// string 'ERROR' parsed by submit client, don't modify!
			if ( $msglevel == LOG_ERR ) {
				echo "<fieldset class=\"error\"><legend>ERROR</legend> " .
					 $msg . "</fieldset>\n";
			} else
			if ( $msglevel == LOG_WARNING ) {
				echo "<fieldset class=\"warning\"><legend>Warning</legend> " .
					$msg . "</fieldset>\n";
			} else {
				echo "<p>" . $msg . "</p>\n";
			}
			// Add strings for non-interactive parsing:
			if ( $msglevel == LOG_ERR ||
			     $msglevel == LOG_WARNING ) echo "\n<!-- @@@$msg@@@ -->\n";
		} else {
			fwrite(STDERR, $stamp . $string . "\n");
			fflush(STDERR);
		}
	}
	if ( $msglevel <= $loglevel ) {
		if ( defined('STDLOG') ) {
			fwrite(STDLOG, $stamp . $string . "\n");
			fflush(STDLOG);
		}
		if ( defined('SYSLOG') ) {
			syslog($msglevel, $string . "\n");
		}
	}
}

/**
 * Log an error at level LOG_ERROR and exit with exitcode 1.
 */
function error($string) {
	logmsg(LOG_ERR, "error: $string");
	exit(1);
}

/**
 * Log a warning at level LOG_WARNING.
 */
function warning($string) {
	logmsg(LOG_WARNING, "warning: $string");
}

/**
 * Handle exceptions by calling error().
 */
function exception_handler($e) {
	error($e->getMessage());
}
