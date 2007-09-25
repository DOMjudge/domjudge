<?php
/**
 * Error handling functions
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( ! defined('SCRIPT_ID') ) {
	define('SCRIPT_ID', basename($_SERVER['PHP_SELF'], '.php'));
}

// is this the webinterface or commandline?
define('IS_WEB', isset($_SERVER['REMOTE_ADDR']));

// Open standard error.
if ( ! IS_WEB && ! defined('STDERR') ) {
	define('STDERR', fopen('php://stderr', 'w'));
}

// Open log file.
if ( defined('LOGFILE') ) {
	if ( $fp = @fopen(LOGFILE, 'a') ) {
		define('STDLOG', $fp);
	} else {
		fwrite(STDERR, "Cannot open log file " . LOGFILE .
			" for appending; continuing without logging.\n");
	}
	unset($fp);
}

// Open syslog connection.
if ( defined('SYSLOG_FACILITY') && !empty(SYSLOG_FACILITY) ) {
    openlog(FALSE, LOG_NDELAY, SYSLOG_FACILITY);
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

/**
 * Log a message $string on the loglevel $msglevel.
 * Prepends a timestamp and logg to the logfile.
 * If this is the web interface: write to the screen with the right CSS class.
 * If this is the command line: write to Standard Error.
 */
function logmsg($msglevel, $string) {
	global $verbose, $loglevel;
    $msg = SCRIPT_ID . ": " . $string . "\n";
    $stamp = "[" . date('M d H:i:s') . "] ";
	if ( $msglevel <= $verbose  ) {
		// if this is the webinterface, print it to stdout, else to stderr
		if ( IS_WEB ) {
			echo "<fieldset class=\"error\"><legend>Error</legend>\n" .
				nl2br(htmlspecialchars($stamp . $msg)) . "</fieldset>\n";
		} else {
			fwrite(STDERR, $stamp . $msg);
			fflush(STDERR);
		}
	}
	if ( $msglevel <= $loglevel && defined('STDLOG') ) {
		fwrite(STDLOG, $stamp . $msg);
		fflush(STDLOG);
	}
    if ( $msglevel <= $loglevel && defined('SYSLOG_FACILITY') && !empty(SYSLOG_FACILITY) ) {
        syslog($msglevel, $msg);
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
