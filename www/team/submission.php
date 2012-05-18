<?php
/**
 * Handle Kattis submission via their api.
 * Api is defined by a python script they call that submits
 * a POST request with relevant (and irrelevant) data.
 * It is in this location so the Kattis team only needs to change
 * their ini, not main code.
 *
 * This is just barely tested and was not yet used in any real setting,
 * and may be incomplete.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submit';

$team = $_POST['teamid'];
$prob = $_POST['problem'];
$lang = $_POST['language'];
$submittime = $_POST['time'];
$extid = $_POST['runid'];
$extresult = 'dummy';

$files = $filenames = array();

for ($i = 1; $i <= $_POST['sourcecount']; $i++) {
	if ( $_FILES["source$i"]['name'] == $_POST['mainfile'] ) {
		$files[]     = $_FILES["source$i"]['tmp_name'];	
		$filenames[] = $_FILES["source$i"]['name'];	
	}
}
if ( empty($filenames) ) die("Main file not found");

for ($i = 1; $i <= $_POST['sourcecount']; $i++) {
	if ( $_FILES["source$i"]['name'] != $_POST['mainfile'] ) {
		$files[]     = $_FILES["source$i"]['tmp_name'];	
		$filenames[] = $_FILES["source$i"]['name'];	
	}
}

echo "Received " . count($filenames) . " files.\n";

$cdata = getCurContest(TRUE);
$cid = $cdata['cid'];

import($team, $prob, $lang, $submittime, $files, $filenames, $extid, $extresult);

echo "Submission done.";

# FIXME: duplicate code with import/import.php
function import($team, $prob, $lang, $submittime, $files, $filenames, $extid, $extresult) {
	if( empty($team) ) error("No value for Team.");
	if( empty($prob) ) error("No value for Problem.");
	if( empty($lang) ) error("No value for Language.");
	if( empty($submittime) ) error("No value for submit time.");
	if( empty($extid) ) error("No value for external submission ID.");
	if( empty($extresult) ) error("No value for external submission verdict.");

	if ( !is_array($files) || count($files)==0 ) error("No files specified.");
	if ( !is_array($filenames) || count($filenames)!=count($files) ) {
		error("Nonmatching (number of) filenames specified.");
	}

	if ( count($filenames)!=count(array_unique($filenames)) ) {
		error("Duplicate filenames detected.");
	}

	global $cdata,$cid, $DB;
	$submittime = strftime(MYSQL_DATETIME_FORMAT, $submittime);

	$duplicate = $DB->q('MAYBEVALUE SELECT externalid FROM submission
			WHERE externalid = %i', $extid);
	if ( $duplicate !== NULL ) {
		$origtime = $DB->q('VALUE SELECT submittime FROM submission
			WHERE externalid = %i', $extid);
		if ( $origtime !== $submittime ) {
			error("duplicate submission ID with different submittime found");
		}
		// update judging result (in case of a rejudge)
		$DB->q('UPDATE submission SET externalresult = %s
			WHERE externalid = %i', $extresult, $extid);
		exit;
	}

	$sourcesize = dbconfig_get('sourcesize_limit');

	if( difftime($cdata['starttime'], $submittime) > 0 ) {
		error("The contest is closed, no submissions accepted. [c$cid]");
	}

	// Check 2: valid parameters?
	if( ! $langid = $DB->q('MAYBEVALUE SELECT langid FROM language WHERE
						  langid = %s AND allow_submit = 1', $lang) ) {
		error("Language '$lang' not found in database or not submittable.");
	}
	if( ! $login = $DB->q('MAYBEVALUE SELECT login FROM team WHERE login = %s',$team) ) {
		error("Team '$team' not found in database.");
	}
	$team = $login;
	if( ! $probid = $DB->q('MAYBEVALUE SELECT probid FROM problem WHERE probid = %s
							AND cid = %i AND allow_submit = "1"', $prob, $cid) ) {
		error("Problem '$prob' not found in database or not submittable [c$cid].");
	}

	// Reindex arrays numerically to allow simultaneously iterating
	// over both $files and $filenames.
	$files     = array_values($files);
	$filenames = array_values($filenames);

	$totalsize = 0;
	for($i=0; $i<count($files); $i++) {
		if ( ! is_readable($files[$i]) ) {
			error("File '".$files[$i]."' not found (or not readable).");
		}
		if ( ! preg_match(FILENAME_REGEX, $filenames[$i]) ) {
			error("Illegal filename '".$filenames[$i]."'.");
		}
		$totalsize += filesize($files[$i]);
	}
	if ( $totalsize > $sourcesize*1024 ) {
		error("Submission file(s) are larger than $sourcesize kB.");
	}

	logmsg (LOG_INFO, "input verified");

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
				  (cid, teamid, probid, langid, submittime, externalid, externalresult)
				  VALUES (%i, %s, %s, %s, %s, %i, %s)',
		     $cid, $team, $probid, $langid, $submittime, $extid, $extresult);

	for($rank=0; $rank<count($files); $rank++) {
		$DB->q('INSERT INTO submission_file
			(submitid, filename, rank, sourcecode) VALUES (%i, %s, %i, %s)',
		       $id, $filenames[$rank], $rank, getFileContents($files[$rank], false));
	}

	// Recalculate scoreboard cache for pending submissions
	calcScoreRow($cid, $team, $probid);

	// Log to event table
	$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid, submitid, description)
		VALUES(%s, %i, %s, %s, %s, %i, "problem submitted")',
	       now(), $cid, $team, $langid, $probid, $id);

	if ( is_writable( SUBMITDIR ) ) {
		// Copy the submission to SUBMITDIR for safe-keeping
		for($rank=0; $rank<count($files); $rank++) {
			$fdata = array('cid' => $cid,
				       'submitid' => $id,
				       'teamid' => $team,
				       'probid' => $probid,
				       'langid' => $langid,
				       'rank' => $rank,
				       'filename' => $filenames[$rank]);
			$tofile = SUBMITDIR . '/' . getSourceFilename($fdata);
			if ( ! @copy($files[$rank], $tofile) ) {
				warning("Could not copy '" . $files[$rank] . "' to '" . $tofile . "'");
			}
		}
	} else {
		logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
	}

	if( difftime($cdata['endtime'], $submittime) <= 0 ) {
		logmsg(LOG_INFO, "The contest is closed, submission stored but not processed. [c$cid]");
	}
}

