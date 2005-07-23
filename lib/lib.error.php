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
$is_web = isset($_SERVER['REMOTE_ADDR']);

if ( ! $is_web && ! defined('STDERR') ) {
	define('STDERR', fopen('php://stderr', 'w'));
}

if ( defined('LOGFILE') ) {
	define('STDLOG', fopen(LOGFILE, 'a'));
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

function logmsg($msglevel, $string) {
	global $verbose, $loglevel,$is_web;
	$msg = "[" . date('M d H:i:s') . "] " . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= $verbose  ) {
		// if this is the webinterface, print it to stdout, else to stderr
		if ( $is_web ) {
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

function error($string) {
	logmsg(LOG_ERR, "error: $string");
	exit(1);
}

function warning($string) {
	logmsg(LOG_WARNING, "warning: $string");
}
