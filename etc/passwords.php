<?php

// $Id$

/** Database login credentials
 *  THIS FILE SHOULD ALWAYS BE NON-READABLE!
 *  (because of database-login usernames/passwords)
 *
 *  Change these from the defaults below to something more difficult to
 *  guess! Also modify this in the MySQL database! This can be done
 *  automatically with 'make gen_passwd' in the SYSTEM_ROOT.
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
		'pass' => 'DOMJUDGE_JURY_PASSWD' ),
	'team'	=> array (		// ...
		'user' => 'domjudge_team',
		'pass' => 'DOMJUDGE_TEAM_PASSWD' ),
	'public'	=> array (	// Select on team,problem,submission,judging,contest
		'user' => 'domjudge_public',
		'pass' => 'DOMJUDGE_PUBLIC_PASSWD' )
	);
