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
error_reporting(E_ALL & ~E_NOTICE);

/** Character set */
define('DJ_CHARACTER_SET', 'utf-8');

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0	=>	'correct',
	101	=>	'compiler-error',
	102	=>	'timelimit',
	103	=>	'run-error',
	104	=>	'no-output',
	105	=>	'wrong-answer',
	106	=>	'presentation-error'
	);

/** Include MySQL database passwords from a separate file */
require('passwords.php');


/*** GLOBAL CONFIG INCLUDE START ***/
/*** GLOBAL CONFIG INCLUDE END ***/

/** Set error reporting to all in debugging mode */
if ( DEBUG & DEBUG_PHP_NOTICE ) error_reporting(E_ALL);
