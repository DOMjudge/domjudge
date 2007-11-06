#!/usr/bin/php -q
<?php
/**
 * Called by commandline/web submitdaemon.
 * Given the details of a submission, check the parameters for validity
 * (is the contest open? is the problem valid? is this really the team?)
 * and if ok, copy the file from INCOMING to SUBMIT and add a database
 * entry.
 *
 * Called: submit_db.php <team> <ip> <problem> <langext> <filename>
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'submit_db');
define ('LOGFILE', LOGDIR.'/submit.log');

require (SYSTEM_ROOT . '/lib/init.php');

// every file written has to be in 0600 filemode
umask(0177);

// Get commandline vars and case-normalize them
$argv = $_SERVER['argv'];

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
$contdata = getCurContest(TRUE);
$cid = $contdata['cid'];

// If no contest has started yet, refuse submissions.
$now = now();
if( $contdata['starttime'] > $now ) {
	error("The contest is closed, no submissions accepted. [c$cid]");
}

// Check 2: valid parameters?
if( ! $lang = $DB->q('MAYBEVALUE SELECT langid FROM language WHERE
                      extension = %s AND allow_submit = 1', $langext) ) {
	error("Language '$langext' not found in database or not submittable.");
}
if( ! $teamrow = $DB->q('MAYBETUPLE SELECT * FROM team WHERE login = %s',$team) ) {
	error("Team '$team' not found in database.");
}
if( ! compareipaddr($teamrow['ipaddress'],$ip) ) {
	if ( $teamrow['ipaddress'] == NULL && ! STRICTIPCHECK ) {
		$DB->q('UPDATE team SET ipaddress = %s WHERE login = %s',$ip,$team);
		logmsg (LOG_NOTICE, "Registered team '$team' at address '$ip'.");
	} else {
		error("Team '$team' not registered at this IP address.");
	}
}
if( ! $probid = $DB->q('MAYBEVALUE SELECT probid FROM problem WHERE probid = %s
                        AND cid = %i AND allow_submit = "1"', $prob, $cid) ) {
	error("Problem '$prob' not found in database or not submittable [c$cid].");
}
if( ! is_readable(INCOMINGDIR."/$file") ) {
	error("File '$file' not found in incoming directory (or not readable).");
}
if( filesize(INCOMINGDIR."/$file") > SOURCESIZE*1024 ) {
	error("Submission file is larger than ".SOURCESIZE." kB."); 
}

logmsg (LOG_INFO, "input verified");

// Copy the submission to a (uniquely generated) file in SUBMITDIR
$template = SUBMITDIR."/$team.$prob.XXXXXX.$langext";
$tofile = mkstemps($template, strlen($langext)+1);

if ( $tofile === FALSE ) {
	error("Could not create tempfile from template: " . $template);
}
$tofile = basename($tofile);

if ( ! copy(INCOMINGDIR."/$file", SUBMITDIR."/$tofile") ) {
	error("Could not copy '".INCOMINGDIR."/".$file.
	      "' to '".SUBMITDIR."/".$tofile."'");
}

// Insert submission into the database
$id = $DB->q('RETURNID INSERT INTO submission
              (cid,teamid,probid,langid,submittime,sourcefile,sourcecode)
              VALUES (%i, %s, %s, %s, %s, %s, %s)',
             $cid, $teamrow['login'], $probid, $lang, $now, $tofile,
             getFileContents(SUBMITDIR."/".$tofile, false));

// Log to event table
$DB->q('INSERT INTO event (cid, teamid, langid, probid, submitid, description)
        VALUES(%i, %s, %s, %s, %i, "problem submitted")',
       $cid, $teamrow['login'], $lang, $probid, $id);

// If the contest has already ended, accept the submission anyway but do not
// process it and notify team.
if( $contdata['endtime'] <= $now ) {
	warning("The contest is closed, submission stored but not processed. [c$cid]");
}

logmsg (LOG_NOTICE, "submitted $team/$prob/$lang, file $tofile, id s$id/c$cid");

exit;
