<?php

require_once("common-config.php");

// Default config

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
define('PROCLIMIT', 15);

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
