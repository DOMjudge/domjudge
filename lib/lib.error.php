<?php

/**
 * Error handling functions
 *
 * $Id$
 */

if ( ! defined('SCRIPT_ID') ) {
	define('SCRIPT_ID', basename($PHP_SELF, '.php'));
}

define('STDERR', fopen('php://stderr', 'w'));

if ( defined('LOGFILE') ) {
	define('STDLOG', fopen(LOGFILE, 'a'));
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

function logmsg($msglevel, $string) {
	global $verbose, $loglevel;
	$msg = "[" . date('M d H:i:s') . "] " . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= $verbose  ) { fwrite(STDERR, $msg); fflush(STDERR); }
	if ( $msglevel <= $loglevel &&
	     defined('STDLOG')      ) { fwrite(STDLOG, $msg); fflush (STDLOG); }
}

function error($string) {
	logmsg(LOG_ERR, "error: $string");
	exit(1);
}

