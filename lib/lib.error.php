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
	define('STDLOG', fopen(LOGFILE, 'w'));
}

function logmsg($msglevel, $string) {
	global $verbose, $loglevel;
	$msg = "[" . date('M d H:i:s') . "] " . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= $verbose  ) { fwrite(STDERR, $msg); }
	if ( $msglevel <= $loglevel &&
	     defined('STDLOG')      ) { fwrite(STDLOG, $msg); }
}

function error($string) {
	logmsg(LOG_ERR, $string);
	exit(1);
}

