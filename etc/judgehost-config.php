<?php

// Default config

/** Loglevels */
error_reporting(E_ALL & ~E_NOTICE);

/** Character set */
define('DJ_CHARACTER_SET', 'utf-8');

define('VERIFICATION_REQUIRED', false);

define('PENALTY_TIME', 20);

/** Restrictions during testing */

// Run solutions in a chroot environment? (gives better security)
define('USE_CHROOT', true);

// Optional script to run for creating/destroying chroot environment,
// leave empty to disable. The default script can be used to support
// Sun Java with a chroot (edit the script first!).
define('CHROOT_SCRIPT', 'chroot-startstop.sh');

// Maximum seconds available for compiling
define('COMPILETIME', 30);

// Maximum memory usage by RUNUSER in kB
// This includes the shell which starts the compiled solution and
// also any interpreter like Sun 'java', which takes 200MB away!
define('MEMLIMIT', 524288);

// Maximum filesize RUNUSER may write in kB
// This should be greater than the maximum testdata output, otherwise
// solutions will abort before writing the complete correct output!!
define('FILELIMIT', 4096);

// Maximum no. processes running as RUNUSER (including shell and
// possibly interpreters)
define('PROCLIMIT', 8);

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0   => 'correct',
	101 => 'compiler-error',
	102 => 'timelimit',
	103 => 'run-error',
	104 => 'no-output',
	105 => 'wrong-answer',
	106 => 'presentation-error',
	107 => 'memory-limit',
	108 => 'output-limit',
	127 => 'internal-error'
	);

/** Priority of results for determining final result with multiple
    testcases. Lower number means higher priority, thus is used first
    as final result. With equal priority, the first occurring result
    determines the final result. */
$RESULTS_PRIO = array(
	1  => 'memory-limit',
	1  => 'output-limit',
	2  => 'run-error',
	3  => 'timelimit',
	4  => 'wrong-answer',
	5  => 'no-output',
	6  => 'presentation-error',
	99 => 'correct',
	);

/** Remap results, e.g. to disable a specific result. Some possible
    options are shown commented below. */
$RESULTS_REMAP = array(
//	'presentation-error' => 'wrong-answer',
//	'presentation-error' => 'correct',
//	'no-output'          => 'wrong-answer',
	
/* sentinel allowing trailing comma's on all previous lines: */
	'correct'            => 'correct'
	);

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
//
// Display judging backend scripts
define('DEBUG_JUDGE', 8);

define('DEBUG', 1);

/** Set error reporting to all in debugging mode */
if ( DEBUG & DEBUG_PHP_NOTICE ) error_reporting(E_ALL);
