#!/usr/bin/php4 -q
<?php
/**
 * $Id$
 */

	
	require ('../etc/config.php');
	require ('../php/init.php');

	umask(0177);

	$argv = $GLOBALS['argv'];
	
	$team = strtolower(@$argv[1]);
	$ip   = @$argv[2];
	$prob = strtolower(@$argv[3]);
	$lang = strtolower(@$argv[4]);
	$file = @$argv[5];


	// Check 0: called correctly?
	if(!$team)	error("No value for team.");
	if(!$ip)	error("No value for ip.");
	if(!$prob)	error("No value for prob.");
	if(!$lang)	error("No value for lang.");
	if(!$file)	error("No value for file.");

	
	// Check 1: is the contest still open?
	if(!$aap = $DB->q('VALUE SELECT starttime <= now() && endtime >= now() FROM contest')) {
		error("The contest is closed, no submissions accepted.");
	}

	// Check 2: valid parameters?
	if(!$langext = $DB->q('MAYBEVALUE SELECT extension FROM language
		                   WHERE langid = %s', $lang) ) {
		error("Language '$lang' not found in database.");
	}
	if(!$row = $DB->q('MAYBETUPLE SELECT * FROM team WHERE login = %s',
		              $team) ) {
		error("Team '$team' not found in database.");
	}
	if($row['ipadres'] != $ip) {
		error("Team '$team' not registered at this IP address.");
	}
	if(!$DB->q('MAYBETUPLE SELECT * FROM problem WHERE probid = %s
		        AND allow_submit = "1"', $prob) ) {
		error("Problem '$prob' not found in database or not submittable.");
	}
	if(!is_readable(INCOMINGDIR."/$file")) {
		error("File '$file' not found in incoming directory.");
	}
	logmsg ("submit_db: input verified");


	// Copy the submission to a (uniquely generated) file in SUBMITDIR
	$dummy = null;
	$tofile = exec(SYSTEM_ROOT."/runprogs/mkstemps.pl ".
	               SUBMITDIR."/$team.$prob.XXXX .$langext", $dummy, $retval);
	if ( $retval != 0 ) error("Could not create tempfile.");
	$tofile = basename($tofile);

	if ( ! copy(INCOMINGDIR."/$file", SUBMITDIR."/$tofile") ) {
		error("Could not copy file to ".SUBMITDIR);
	}

	// Insert submission into the database	
	$id = $DB->q('RETURNID INSERT INTO submission 
		(team,probid,langid,submittime,source)
		VALUES (%s, %s, %s, NOW(), %s)',
		$team, $prob, $lang, $tofile);

	logmsg ("submit_db: submitted $team/$prob/$lang, file $tofile, id $id");

	exit;
