<?php

/**
 * Error handling functions
 *
 * $Id$
 */
if ( ! defined('SCRIPT_ID') ) {
	define('SCRIPT_ID', basename($_SERVER['PHP_SELF'], '.php'));
}

// is this the webinterface or commandline?
define('IS_WEB', isset($_SERVER['REMOTE_ADDR']));

if ( ! IS_WEB && ! defined('STDERR') ) {
	define('STDERR', fopen('php://stderr', 'w'));
}

if ( defined('LOGFILE') ) {
	if ( $fp = @fopen(LOGFILE, 'a') ) {
		define('STDLOG', $fp);
	} else {
		fwrite(STDERR, "Cannot open log file " . LOGFILE .
			" for appending; continuing without logging.\n");
	}
	unset($fp);
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
	$msg = "[" . date('M d H:i:s') . "] " . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= $verbose  ) {
		// if this is the webinterface, print it to stdout, else to stderr
		if ( IS_WEB ) {
			echo "<fieldset class=\"error\"><legend>Error</legend>\n" .
				htmlspecialchars($msg) . "</fieldset>\n";
		} else {
			fwrite(STDERR, $msg);
			fflush(STDERR);
		}
	}
	if ( $msglevel <= $loglevel && defined('STDLOG') ) {
		fwrite(STDLOG, $msg);
		fflush(STDLOG);
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
