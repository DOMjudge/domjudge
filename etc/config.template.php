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

/** Database login credentials */
$DBDATA = array (
	'jury'	=> array (				// s/i/u/d on each table
		'host' => 'judge.nkp.nl',
		'db'   => 'nkpjury',
		'user' => 'nkp',
		'pass' => '=2nP4iVns' ),
	'team'	=> array (				// ...
		'host' => 'judge.nkp.nl',
		'db'   => 'nkpjury',
		'user' => 'nkp_team',
		'pass' => 'gasI87^cR' ),
	'public'	=> array (			// Select on team,problem,submission,juding,contest
		'host' => 'judge.nkp.nl',
		'db'   => 'nkpjury',
		'user' => 'nkp_public',
		'pass' => 'yrJ84cjU~' )
	);

