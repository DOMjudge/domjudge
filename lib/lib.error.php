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
	$msg = '[' . date('M d H:i:s') . '] ' . SCRIPT_ID . ": ". $string . "\n";
	if ( $msglevel <= LOG_NOTICE ) { fwrite(STDERR, $msg); }
	if ( $msglevel <= LOG_DEBUG  ) { fwrite(STDLOG, $msg); }
}

function error($string) {
	logmsg(LOG_ERR, $string);
	exit(1);
}

