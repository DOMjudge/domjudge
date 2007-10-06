<?php

/* $Id$ */

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
	$now = $DB->q('SELECT * FROM contest
	               WHERE starttime <= NOW() AND endtime >= NOW()');
	
	if ( $now->count() == 1 ) {
		$row = $now->next();
		$retval = ( $fulldata ? $row : $row['cid'] );
	}
	if ( $now->count() == 0 ) {
		$prev = $DB->q('SELECT * FROM contest
		                WHERE endtime <= NOW() ORDER BY endtime DESC LIMIT 1');
		if ( $prev->count() == 0 ) return FALSE;
		$row = $prev->next();
		$retval = ( $fulldata ? $row : $row['cid'] );
	}
	if ( $now->count() > 1 ) {
		error("Contests table contains overlapping contests");
	}
	
	return $retval;
}

/**
 * Scoreboard calculation
 *
 * This is here because it needs to be called by the submit_db script
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
	                  (c.lastscoreupdate IS NOT NULL && submittime >= c.lastscoreupdate) AS afterfreeze
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

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_public
	        (cid, teamid, probid, submissions, totaltime, penalty, is_correct, balloon)
	        VALUES (%i,%s,%s,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $time_p, $penalty_p, $correct_p, $balloon);

	// insert or update the values in the public/team scores table
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
	return date('Y-m-d H-i-s');
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
