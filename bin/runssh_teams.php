#!/usr/bin/php -q
<?php
/**
 * Program to run a specific command on all team accounts using ssh.
 * 
 * Usage: $0 <program>
 *
 * $Id$
 */
	require ('../etc/config.php');

	define ('SCRIPT_ID', 'runssh_teams');
	define ('LOGFILE', LOGDIR.'/check.log');

	require ('../lib/init.php');
	
	$argv = $_SERVER['argv'];

	$program = @$argv[1];

	if ( ! $program ) error("No program specified");

	logmsg(LOG_DEBUG, "running program '$program'");

	$teams = $DB->q('COLUMN SELECT login FROM team');

	foreach($teams as $team) {
		logmsg(LOG_DEBUG, "running on account '$team'");
		system("ssh -l $team localhost $program",$exitcode);
		if ( $exitcode != 0 ) {
			logmsg(LOG_NOTICE, "on '$team': exitcode $exitcode");
		}
	}

	logmsg(LOG_NOTICE, "finished");

	exit;
