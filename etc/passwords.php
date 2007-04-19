<?php

// $Id$

/** Database login credentials
 *  THIS FILE SHOULD ALWAYS BE NON-READABLE!
 *  (because of database-login usernames/passwords)
 *
 *  These passwords are initialised automatically when running
 *  'make install' in the SYSTEM_ROOT and removed when running
 *  'make distclean'.
 */

$DBLOGIN = array (
	'jury'   => array (	// s/i/u/d on each table
		'user' => 'domjudge_jury', 'pass' => 'DOMJUDGE_JURY_PASSWD'
		),
	'team'   => array (	// ...
		'user' => 'domjudge_team', 'pass' => 'DOMJUDGE_TEAM_PASSWD'
		),
	'public' => array (	// Select on team,problem,submission,judging,contest
		'user' => 'domjudge_public', 'pass' => 'DOMJUDGE_PUBLIC_PASSWD'
		)
	);
