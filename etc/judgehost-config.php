<?php

// Default config

/** Loglevels */
define_syslog_variables();
error_reporting(E_ALL & ~E_NOTICE);

/** Character set */
define('DJ_CHARACTER_SET', 'utf-8');

define('VERIFICATION_REQUIRED', false);

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0	=>	'correct',
	101	=>	'compiler-error',
	102	=>	'timelimit',
	103	=>	'run-error',
	104	=>	'no-output',
	105	=>	'wrong-answer',
	106	=>	'presentation-error',
	107	=>	'memory-limit',
	108	=>	'output-limit'
	);

define('BEEP_CMD', '/usr/bin/beep');
define('BEEP_ERROR', '-l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n -l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n -l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n -l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000 -n -l 1000 -d 500 -f 800 -n -l 1000 -d 500 -f 1000');
define('BEEP_WARNING', '-l 1000 -d 500 -f 300 -n -l 1000 -d 500 -f 200 -n -l 1000 -d 500 -f 300 -n -l 1000 -d 500 -f 200');
define('BEEP_SUBMIT', '-f 400 -l 100 -n -f 400 -l 70');
define('BEEP_ACCEPT', '-f 400 -l 100 -n -f 500 -l 70');
define('BEEP_REJECT', '-f 400 -l 100 -n -f 350 -l 70');

// Set DEBUG as a bitmask of the following settings.
// Of course never to be used on live systems!
//
// Display PHP notice level warnings
define('DEBUG_PHP_NOTICE', 1);
//
// Display timings for loading webpages
define('DEBUG_TIMINGS', 2);
//
// Display SQL queries on webpages
define('DEBUG_SQL', 4);

define('DEBUG', 1);

/** Set error reporting to all in debugging mode */
if ( DEBUG & DEBUG_PHP_NOTICE ) error_reporting(E_ALL);
