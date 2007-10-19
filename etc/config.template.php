<?php

/* Configuration file for PHP-scripts
 * $Id$
 */

/*** AUTOGENERATE HEADER START ***/

// This is a template configuration file.
//
// Use `generate_config.sh' to generate the corresponding config file
// from the global config file.

/*** AUTOGENERATE HEADER END ***/

/** Loglevels */
define_syslog_variables();
error_reporting(E_ALL);

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0	=>	'correct',
	101	=>	'compiler-error',
	102	=>	'timelimit',
	103	=>	'run-error',
	104	=>	'no-output',
	105	=>	'wrong-answer'
	);

/** Include MySQL database passwords from a separate file */
require('passwords.php');


/*** GLOBAL CONFIG INCLUDE START ***/
/*** GLOBAL CONFIG INCLUDE END ***/
