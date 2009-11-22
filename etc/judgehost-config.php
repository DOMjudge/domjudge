<?php

require_once("common-config.php");

// Default config

/** Restrictions during testing */

// Run solutions in a chroot environment? (gives better security)
define('USE_CHROOT', true);

// Optional script to run for creating/destroying chroot environment,
// leave empty to disable. This example script can be used to support
// Sun Java with a chroot (edit the script first!).
// define('CHROOT_SCRIPT', 'chroot-startstop.sh');
define('CHROOT_SCRIPT', '');

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

// Priority of results for determining final result with multiple
// testcases. Lower number means higher priority, thus is used first
// as final result. With equal priority, the first occurring result
// determines the final result.
// The default below tries to simulate single testcase behaviour.
$RESULTS_PRIO = array(
	'memory-limit'       =>  1,
	'output-limit'       =>  1,
	'run-error'          =>  1,
	'timelimit'          =>  1,
	'wrong-answer'       => 11,
	'presentation-error' => 12,
	'no-output'          => 13,
	'correct'            => 99,
	);

/** Remap results, e.g. to disable a specific result. Some possible
    options are shown commented below. */
$RESULTS_REMAP = array(
//	'presentation-error' => 'wrong-answer',
//	'presentation-error' => 'correct',
//	'no-output'          => 'wrong-answer',
	);
