#!/usr/bin/php -q
<?php
/**
 * Called by submitdaemon.pl
 * Given the details of a submission, check the parameters for validity
 * (is the contest open? is the problem valid? is this really the team?)
 * and if ok, copy the file from INCOMING to SUBMIT and add a database
 * entry.
 *
 * Called: submit_db.php <team> <ip> <problem> <langext> <filename>
 *
 * $Id$
 */
	require ('../etc/config.php');

	define ('SCRIPT_ID', 'submit_db');
	define ('LOGFILE', LOGDIR.'/submit.log');

	require ('../lib/init.php');
	
	// every file written has to be in 0600 filemode
	umask(0177);

	// Get commandline vars and case-normalize them
	$argv = $GLOBALS['argv'];
	
	$team    = strtolower(@$argv[1]);
	$ip      = @$argv[2];
	$prob    = strtolower(@$argv[3]);
	$langext = strtolower(@$argv[4]);
	$file    = @$argv[5];

	logmsg(LOG_DEBUG, "arguments: '$team' '$ip' '$prob' '$langext' '$file'");


	// Check 0: called correctly?
	if( ! $team    ) error("No value for Team.");
	if( ! $ip      ) error("No value for IP.");
	if( ! $prob    ) error("No value for Problem.");
	if( ! $langext ) error("No value for Language.");
	if( ! $file    ) error("No value for Filename.");


	// Check 1: is the contest open?
	$cont = $DB->q('MAYBETUPLE SELECT *,
		UNIX_TIMESTAMP(starttime) as start_u, UNIX_TIMESTAMP(endtime) as end_u
		FROM contest ORDER BY starttime DESC LIMIT 1');
	if( ! $cont ) {
		error("No contest found in the database, aborting.");
	}
	if( $cont['start_u'] > time() || $cont['end_u'] < time() ) {
		error("The contest is closed, no submissions accepted. [c$cont[cid]]");
	}


	// Check 2: valid parameters?
	if( ! $lang = $DB->q('MAYBEVALUE SELECT langid FROM language WHERE
		                   extension = %s AND allow_submit = 1', $langext) ) {
		error("Language '$langext' not found in database or not submittable.");
	}
	if( ! $teamrow = $DB->q('MAYBETUPLE SELECT * FROM team WHERE login = %s',
	                        $team) ) {
		error("Team '$team' not found in database.");
	}
	if( $teamrow['ipaddress'] != $ip ) {
		error("Team '$team' not registered at this IP address.");
	}
	if( ! $DB->q('MAYBETUPLE SELECT * FROM problem WHERE probid = %s
	              AND allow_submit = "1"', $prob) ) {
		error("Problem '$prob' not found in database or not submittable.");
	}
	if( ! is_readable(INCOMINGDIR."/$file") ) {
		error("File '$file' not found in incoming directory (or not readable).");
	}
	if( filesize(INCOMINGDIR."/$file") > SOURCESIZE*1024 ) {
		error("Submission file is larger than ".SOURCESIZE." kB."); 
	}

	logmsg (LOG_INFO, "input verified");


	// Copy the submission to a (uniquely generated) file in SUBMITDIR
	$dummy = null;
	$tofile = exec(SYSTEM_ROOT."/bin/mkstemps ".
	               SUBMITDIR."/$team.$prob.XXXXXX.$langext", $dummy, $retval);
	if ( $retval != 0 ) error("Could not create tempfile.");
	$tofile = basename($tofile);

	if ( ! copy(INCOMINGDIR."/$file", SUBMITDIR."/$tofile") ) {
		error("Could not copy '".INCOMINGDIR."/".$file.
		                "' to '".SUBMITDIR."/".$tofile."'");
	}

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
	              (cid,team,probid,langid,submittime,sourcefile,sourcecode)
	              VALUES (%i, %s, %s, %s, NOW(), %s, %s)',
	             $cont["cid"], $team, $prob, $lang, $tofile,
	             get_content(SUBMITDIR."/".$tofile));

	logmsg (LOG_NOTICE, "submitted $team/$prob/$lang, file $tofile, id s$id");

	exit;
