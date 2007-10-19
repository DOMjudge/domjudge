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
/**
 * Which users to grant 'admin' status on the jury web interface.
 * Judges can view all information, write clarifications, verify
 * and rejudge submissions.
 *
 * Admins can do the same, plus update most of the contest- and
 * system information.
 *
 * Defaults to 'domjudge_jury', so no difference between judges
 * and admins, but if desired you can add extra accounts
 * to the htaccess, and list here the ones you wish to have admin
 * privilege.
 */
$DOMJUDGE_ADMINS = array ('domjudge_jury');

