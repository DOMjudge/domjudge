<?php

// DEZE FILE MOET ALTIJD DICHT STAAN!
// (ivm database-logingegevens)

/**
 * Configuration file for PHP-scripts
 * $Id$
 */

/** Define absulote paths used for finding other files */
define('SYSTEM_ROOT', $_ENV['HOME'].'/systeem/svn/jury');
define('OUTPUT_ROOT', $_ENV['HOME'].'/systeem/systest');
define('INPUT_ROOT',  $_ENV['HOME'].'/opgaven');

/** Paths within OUTPUT_ROOT */
define('INCOMINGDIR', OUTPUT_ROOT.'/incoming');
define('SUBMITDIR',   OUTPUT_ROOT.'/sources');
define('JUDGEDIR',    OUTPUT_ROOT.'/judging');
define('LOGDIR',      OUTPUT_ROOT.'/log');

/** Loglevels */
define_syslog_variables();

global $verbose, $loglevel;
$verbose = LOG_NOTICE;
$loglevel = LOG_DEBUG;

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

