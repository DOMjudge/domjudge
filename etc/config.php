<?php

/**
 * Configuration file for PHP-scripts
 * $Id$
 */

/** Define absulote paths used for finding other files */
define('SYSTEM_ROOT', '/home/cies/nkp0405/systeem/svn');
define('OUTPUT_ROOT', '/home/cies/nkp0405/systeem/systest');

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0	=>	'correct',
	1	=>	'compiler-error',
	2	=>	'timelimit',
	3	=>	'run-error',
	4	=>	'no-output',
	5	=>	'wrong-answer'
	);

/** init.php is always needed, so we include it here */
require(SYSTEM_ROOT . '/php/init.php');
