<?php

// THIS FILE SHOULD ALWAYS BE NON-READABLE!
// (because of database-login usernames/passwords)

/* Configuration file for PHP-scripts
 * $Id$
 */

/*** GLOBAL CONFIG HEADER START ***/

// This is a template configuration file.
//
// Use `generate_config.sh' to generate the corresponding config file
// from the global config file.

/*** GLOBAL CONFIG HEADER END ***/

/** Loglevels */
define_syslog_variables();

/** Possible exitcodes from testsol and their meaning */
$EXITCODES = array (
	0	=>	'correct',
	1	=>	'compiler-error',
	2	=>	'timelimit',
	3	=>	'run-error',
	4	=>	'no-output',
	5	=>	'wrong-answer'
	);

/*** GLOBAL CONFIG MAIN START ***/
/*** GLOBAL CONFIG MAIN END ***/

/** Database login credentials */
$DBDATA = array (
	'jury'	=> array (				// s/i/u/d on each table
		'host' => 'alan',
		'db'   => 'nkpjury',
		'user' => 'nkp',
		'pass' => 'JudgeJudy' ),
	'team'	=> array (				// ...
		'host' => 'localhost',
		'db'   => 'nkpjury',
		'user' => 'nkp_team',
		'pass' => 'xb8*$Spp[2j4' ),
	'public'	=> array (			// Select on team,problem,submission,juding,contest
		'host' => 'localhost',
		'db'   => 'nkpjury',
		'user' => 'nkp_public',
		'pass' => '4nG47c!h&;090f' )
	);
