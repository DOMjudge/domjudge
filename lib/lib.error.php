<?php

/**
 * Error handling functions
 *
 * $Id$
 */

if(!defined('LOG_ID')) {
	define('LOG_ID', SCRIPT_ID);
}

define('STDERR', fopen('php://stderr', 'w'));
define('STDLOG', fopen(LOGDIR.'/'.LOG_ID, 'w'));

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

