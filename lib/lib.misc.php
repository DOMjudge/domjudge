<?php

/* $Id$ */

/**
 * helperfunction to read 50,000 bytes from a file
 */
function getFileContents($filename) {

	if ( ! file_exists($filename) ) return '';
	$fh = fopen($filename,'r');
	if ( ! $fh ) error("Could not open $filename for reading");

	$note = (filesize($filename) > 50000 ? "\n[output truncated after 50.000 B]\n" : '');
	
	return fread($fh, 50000) . $note;
}

/**
 * Will return either the current contest, or else the upcoming one
 */
function getCurContest() {

	global $DB;
	$now = $DB->q('SELECT cid FROM contest
		WHERE starttime <= NOW() AND endtime >= NOW()');
	
	if ( $now->count() == 1 ) {
		$row = $now->next();
		$curcontest = $row['cid'];
	}
	if ( $now->count() == 0 ) {
		$prev = $DB->q('SELECT cid FROM contest
			WHERE endtime <= NOW() ORDER BY endtime DESC LIMIT 1');	
		$row = $prev->next();
		$curcontest = $row['cid'];
	}
	if ( $now->count() > 1 ) {
		error("Contests table contains overlapping contests");
	}
	
	return $curcontest;
}

/**
 * Add another key/value to a url
 */
function addUrl($url, $keyvalue, $encode = TRUE)
{
	$separator = (strrpos($url, '?')===False) ? '?' : ( $encode ? '&amp;' : '&');
	return $url . $separator . $keyvalue;
}

/**
 * Scoreboard calculation
 *
 * This is here because it needs to be called by the submit_db script
 * as well.  
 *
 * Given a contestid, teamid and a problemid,
 * (re)calculate the values for one row in the scoreboard.
 */
function calcScoreRow($cid, $team, $prob) {
	global $DB;

	$result = $DB->q('SELECT result, 
		(UNIX_TIMESTAMP(submittime)-UNIX_TIMESTAMP(c.starttime))/60 AS timediff,
		(c.lastscoreupdate IS NOT NULL &&
		 submittime >= c.lastscoreupdate) AS afterfreeze
		FROM judging
		LEFT JOIN submission s USING(submitid)
		LEFT OUTER JOIN contest c ON(c.cid=s.cid)
		WHERE team = %s AND probid = %s AND valid = 1 AND result IS NOT NULL
		AND s.cid = %i ORDER BY submittime', $team, $prob, $cid);

	// reset vars
	$submitted_j = $penalty_j = $time_j = $correct_j = 0;
	$submitted_p = $penalty_p = $time_p = $correct_p = 0;

	// for each submission
	while( $row = $result->next() ) {

		$submitted_j++;
		if ( ! $row['afterfreeze'] ) $submitted_p++;

		// if correct, don't look at any more submissions after this one
		if($row['result'] == 'correct') {

			$correct_j = 1;
			$time = round((int)@$row['timediff']);
			if ( ! $row['afterfreeze'] ) {
				$correct_p = 1;
				$time_p = round((int)@$row['timediff']);
			}
			break;
		}

		// 20 penalty minutes for each submission
		// (will only be counted if this problem is correctly solved)
		$penalty_j += PENALTY_TIME;
		if ( ! $row['afterfreeze'] ) $penalty_p += PENALTY_TIME;
		
	}

	// calculate penalty time: only when correct add it to the total
	if ( $correct_j == 0 ) $penalty_j = 0;
	if ( $correct_f == 0 ) $penalty_p = 0;

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scoreboard_jury
		(cid, team, problem, submissions, totaltime, penalty, is_correct )
		VALUES (%i,%s,%s,%i,%i,%i,%i)',
		$cid, $team, $prob, $submitted_j, $time_j, $penalty_j, $correct_j);
	
	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scoreboard_public
		(cid, team, problem, submissions, totaltime, penalty, is_correct )
		VALUES (%i,%s,%s,%i,%i,%i,%i)',
		$cid, $team, $prob, $submitted_p, $time_p, $penalty_p, $correct_p);

	return;
}
