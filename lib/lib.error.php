<?php

/**
 * Error handling functions
 *
 * $Id$
 */

if(!defined('SCRIPT_ID')) {
	define('SCRIPT_ID', basename($PHP_SELF, '.php'));
}
if(!defined('LOGFILE')) {
	define('LOGFILE', SCRIPT_ID);
}

define('STDERR', fopen('php://stderr', 'w'));
define('STDLOG', fopen(LOGDIR.'/'.LOGFILE, 'w'));

function logmsg($msglevel, $string) {
	global $verbose, $loglevel;
	$msg = '[' . date('M d H:i:s') . '] ' . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= $verbose  ) { fwrite(STDERR, $msg); }
	if ( $msglevel <= $loglevel ) { fwrite(STDLOG, $msg); }
}

function error($string) {
	logmsg(LOG_ERR, $string);
	exit(1);
}

