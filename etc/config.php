<?php

// DEZE FILE MOET ALTIJD DICHT STAAN!
// (ivm database-logingegevens)

/**
 * Configuration file for PHP-scripts
 * $Id$
 */

/** Define absulote paths used for finding other files */
define('SYSTEM_ROOT', '/home/cies/nkp0405/systeem/svn/jury');
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

/** Database login credentials */
$DBDATA = array (
	'jury'	=> array (				// s/i/u/d on each table
		'host' => 'localhost',
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

