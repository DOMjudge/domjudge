<?php

// THIS FILE SHOULD ALWAYS BE NON-READABLE!
// (because of database-login usernames/passwords)

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
	1	=>	'compiler-error',
	2	=>	'timelimit',
	3	=>	'run-error',
	4	=>	'no-output',
	5	=>	'wrong-answer'
	);

/*** GLOBAL CONFIG INCLUDE START ***/
/*** GLOBAL CONFIG INCLUDE END ***/

/** Database login credentials
 *
 *  Change these from the defaults below to something more difficult to
 *  guess! Also modify this in the MySQL database!
 *
 *  The 'domjudge_team' and 'domjudge_public' passwords can be set to
 *  random strings, because these are only used by internal scripts.
 *  The 'domjudge_jury' password you might want to set to something not
 *  too difficult to remember, because you need this password (or the
 *  MySQL root-password) to log into the MySQL database to change things
 *  there directly.
 */
$DBLOGIN = array (
	'jury'	=> array (		// s/i/u/d on each table
		'user' => 'domjudge_jury',
		'pass' => 'jury' ),
	'team'	=> array (		// ...
		'user' => 'domjudge_team',
		'pass' => 'team' ),
	'public'	=> array (	// Select on team,problem,submission,judging,contest
		'user' => 'domjudge_public',
		'pass' => 'public' )
	);
