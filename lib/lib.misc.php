<?php
/**
 * Miscellaneous helper functions
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */


/** Constant to define MySQL datetime format in PHP date() function notation. */
define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');


/**
 * helperfunction to read all contents from a file.
 * If $sizelimit is true (default), then only limit this to
 * the first 50,000 bytes and attach a note saying so.
 */
function getFileContents($filename, $sizelimit = true) {

	if ( ! file_exists($filename) ) {
		return '';
	}
	if ( ! is_readable($filename) ) {
		error("Could not open $filename for reading: not readable");
	}

	// in PHP 5.1.0+ we can just use file_get_contents() with the maxlen parameter.
	if ( $sizelimit && filesize($filename) > 50000 ) {
		$fh = fopen($filename,'r');
		if ( ! $fh ) error("Could not open $filename for reading");
		$ret = fread($fh, 50000) . "\n[output truncated after 50,000 B]\n";
		fclose($fh);
		return $ret;
	}

	return file_get_contents($filename);
}

/**
 * Will return either the current contest id, or
 * the most recently finished one.
 * When fulldata is true, returns the total row as an array
 * instead of just the ID.
 */
function getCurContest($fulldata = FALSE) {

	global $DB;
	$now = $DB->q('MAYBETUPLE SELECT * FROM contest
	               WHERE activatetime <= NOW() ORDER BY activatetime DESC LIMIT 1');

	if ($now == NULL)
		return FALSE;
	else
		return ( $fulldata ? $now : $now['cid'] );
}

/**
 * Scoreboard calculation
 *
 * This is here because it needs to be called by the judgedaemon script
 * as well.
 *
 * Given a contestid, teamid and a problemid,
 * (re)calculate the values for one row in the scoreboard.
 *
 * Due to current transactions usage, this function MUST NOT contain
 * any START TRANSACTION or COMMIT statements.
 */
function calcScoreRow($cid, $team, $prob) {
	global $DB;

	$result = $DB->q('SELECT result, verified, 
	                  (UNIX_TIMESTAMP(submittime)-UNIX_TIMESTAMP(c.starttime))/60 AS timediff,
	                  (c.freezetime IS NOT NULL && submittime >= c.freezetime) AS afterfreeze
	                  FROM judging
	                  LEFT JOIN submission s USING(submitid)
	                  LEFT OUTER JOIN contest c ON(c.cid=s.cid)
	                  WHERE teamid = %s AND probid = %s AND valid = 1 AND
	                  result IS NOT NULL AND s.cid = %i ORDER BY submittime',
	                 $team, $prob, $cid);

	$balloon = $DB->q('MAYBEVALUE SELECT balloon FROM scoreboard_jury
                       WHERE cid = %i AND teamid = %s AND probid = %s',
	                  $cid, $team, $prob);
	
	if ( ! $balloon ) $balloon = 0;
	
	// reset vars
	$submitted_j = $penalty_j = $time_j = $correct_j = 0;
	$submitted_p = $penalty_p = $time_p = $correct_p = 0;

	// for each submission
	while( $row = $result->next() ) {

		if ( VERIFICATION_REQUIRED && ! $row['verified'] ) continue;
		
		$submitted_j++;
		if ( ! $row['afterfreeze'] ) $submitted_p++;

		// if correct, don't look at any more submissions after this one
		if ( $row['result'] == 'correct' ) {

			$correct_j = 1;
			$time_j = round((int)@$row['timediff']);
			if ( ! $row['afterfreeze'] ) {
				$correct_p = 1;
				$time_p = round((int)@$row['timediff']);
			}
			// if correct, we don't add penalty time for any later submissions
			break;
		}

		// extra penalty minutes for each submission
		// (will only be counted if this problem is correctly solved)
		$penalty_j += PENALTY_TIME;
		if ( ! $row['afterfreeze'] ) $penalty_p += PENALTY_TIME;
		
	}

	// calculate penalty time: only when correct add it to the total
	if ( $correct_j == 0 ) $penalty_j = 0;
	if ( $correct_p == 0 ) $penalty_p = 0;

	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scoreboard_public
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct)
	        VALUES (%i,%s,%s,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $time_p, $penalty_p, $correct_p);

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_jury
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct, balloon)
	        VALUES (%i,%s,%s,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_j, $time_j, $penalty_j, $correct_j, $balloon);

	return;
}

/**
 * Simulate MySQL NOW() function to create insert queries that do not
 * change when replicated later.
 */
function now()
{
	return date(MYSQL_DATETIME_FORMAT);
}

/**
 * Wrapper function to call beep with one of the predefined settings.
 */
function beep($beeptype)
{
	system(BEEP_CMD . " " . $beeptype . " &");
}

/**
 * Create a unique file from a template string.
 *
 * Returns a full path to the filename or FALSE on failure.
 */
function mkstemps($template, $suffixlen)
{
	if ( $suffixlen<0 || strlen($template)<$suffixlen+6 ) return FALSE;

	if ( substr($template,-($suffixlen+6),6)!='XXXXXX' ) return FALSE;

	$letters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$TMP_MAX = 16384;

	umask(0133);
	
	for($try=0; $try<$TMP_MAX; $try++) {
		$value = mt_rand();

		$filename = $template;
		$pos = strlen($filename)-$suffixlen-6;
		
		for($i=0; $i<6; $i++) {
			$filename{$pos+$i} = $letters{$value % 62};
			$value /= 62;
		}

		$fd = @fopen($filename,"x");

		if ( $fd !== FALSE ) {
			fclose($fd);
			return $filename;
		}
	}

	// We couldn't create a non-existent filename from the template:
	return FALSE;
}

/**
 * Compares two IP addresses for equivalence (simple equality test for
 * IPv4-only addresses at this moment).
 */
function compareipaddr($ip1, $ip2)
{
	return $ip1==$ip2;
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler($signal)
{
	global $exitsignalled;

	logmsg(LOG_DEBUG, "Signal $signal received");
	
	switch ( $signal ) {
	case SIGTERM:
	case SIGHUP:
	case SIGINT:
		$exitsignalled = TRUE;
	}
}

function initsignals()
{
	global $exitsignalled;

	$exitsignalled = FALSE;
	
	if ( ! function_exists('pcntl_signal') ) {
		logmsg(LOG_INFO, "Signal handling not available");
		return;
	}

	logmsg(LOG_DEBUG, "Installing signal handlers");

	// Install signal handler for TERMINATE, HANGUP and INTERRUPT
	// signals. The sleep() call will automatically return on
	// receiving a signal.
	pcntl_signal(SIGTERM,"sig_handler");
	pcntl_signal(SIGHUP, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
}

/**
 * This function takes a temporary file of a submission,
 * validates it and puts it into the database. Additionally it
 * moves it to a backup storage.
 */
function submit_solution($team, $ip, $prob, $langext, $file)
{
	if( empty($team)    ) error("No value for Team.");
	if( empty($ip)      ) error("No value for IP.");
	if( empty($prob)    ) error("No value for Problem.");
	if( empty($langext) ) error("No value for Language.");
	if( empty($file)    ) error("No value for Filename.");

	global $cdata,$cid, $DB;

	// If no contest has started yet, refuse submissions.
	$now = now();
	
	if( strcmp($cdata['starttime'], $now) > 0 ) {
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
	if( ! is_readable($file) ) {
		error("File '$file' not found (or not readable).");
	}
	if( filesize($file) > SOURCESIZE*1024 ) {
		error("Submission file is larger than ".SOURCESIZE." kB."); 
	}

	logmsg (LOG_INFO, "input verified");

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
				  (cid, teamid, probid, langid, submittime, sourcecode)
				  VALUES (%i, %s, %s, %s, %s, %s)',
				 $cid, $team, $prob, $langext, $now,
				 getFileContents($file, false));

	// Log to event table
	$DB->q('INSERT INTO event (cid, teamid, langid, probid, submitid, description)
			VALUES(%i, %s, %s, %s, %i, "problem submitted")',
		   $cid, $team, $langext, $prob, $id);

	$tofile = getSourceFilename($cid,$id,$team,$prob,$langext);
	$topath = SUBMITDIR . "/$tofile";

	if ( is_writable ( SUBMITDIR ) ) {
		// Copy the submission to SUBMITDIR for safe-keeping
		if ( ! copy($file, $topath) ) {
			error("Could not copy '" . $file.
				  "' to '" . $topath . "'");
		}
		$writtenfile = ", file $tofile";
	} else {
		logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
		$writtenfile = "";
	}

	if( strcmp($cdata['endtime'], $now) <= 0 ) {
		warning("The contest is closed, submission stored but not processed. [c$cid]");
	}

	logmsg (LOG_NOTICE, "submitted $team/$prob/$lang$writtenfile, id s$id/c$cid");
}

/**
 * Compute the filename of a given submission.
 */
function getSourceFilename($cid,$sid,$team,$prob,$langext)
{
	return "c$cid.s$sid.$team.$prob.$langext";
}


