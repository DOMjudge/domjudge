<?php

/**
 * Common functions in jury interface
 *
 * $Id$
 */


/**
 * Outputs a list of judgings, limited by key=value
 *
 * $key can only be one of (submitid,judgehost)
 */
function putJudgings($key, $value) {
	global $DB;
	
	if ( empty($key) || empty($value) ) {
		error("no key or value passed for selection in judging output");
	}

	// get the judgings for a specific key and value pair
	// select only specific fields to avoid retrieving large blobs
	$res = $DB->q('SELECT judgingid, submitid, starttime, endtime, judgehost,
	               result, verified, valid FROM judging
	               WHERE cid = %i AND ' .
				   ( $key == 'submitid' ? 'submitid = %s' : '' ) .
				   ( $key == 'judgehost' ? 'judgehost = %s' : '' ) .
				   ' ORDER BY starttime DESC',
	              getCurContest(), $value);

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table class=\"list\">\n<tr><th>ID</th><th>start</th><th>end</th>";
		if ( $key != 'judge' ) echo "<th>judge</th>";
		echo "<th>result</th><th>valid</th><th>verified</th>";
		echo "</tr>\n";
		while( $jud = $res->next() ) {
			echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] .
				'">j' .	(int)$jud['judgingid'] . '</a></td>';
			echo '<td>' . printtime($jud['starttime']) . '</td>';
			echo '<td>' . printtime(@$jud['endtime'])  . '</td>';
			echo '<td><a href="judgehost.php?id=' . urlencode(@$jud['judgehost']) .
				'">' . printhost(@$jud['judgehost']) . '</a></td>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] . '">' .
				printresult(@$jud['result'], $jud['valid']) . '</a></td>';
			echo '<td align="center">' . printyn($jud['valid']) . '</td>';
			echo '<td align="center">' . printyn($jud['verified']) . '</td>';
			echo "</tr>\n";
		}
		echo "</table>\n\n";
	}

	return;
}

/**
 * Marks a set of submissions for rejudging, limited by key=value
 * key has to be a full quantifier, e.g. "submission.team"
 *
 * $key must be one of (judging.judgehost, submission.team, submission.probid,
 * submission.langid, submission.submitid)
 */
function rejudge($key, $value) {
	global $DB;

	if ( empty($key) || empty($value) ) {
		error("no key or value passed for selection in rejudging");
	}
	
	$cid = getCurContest();

	// Using MySQL >= 4.0.4:
/*
	$DB->q('UPDATE judging
	        LEFT JOIN submission ON (submission.submitid = judging.submitid)
	        SET valid = 0, judgehost = NULL, judgemark = NULL
	        WHERE judging.cid = %i AND valid = 1 AND
	        ( result IS NULL OR result != "correct" ) AND ' .
	        ( $key == 'judging.judgehost' ? 'judging.judgehost = %s' : '' ) .
	        ( $key == 'submission.team' ? 'submission.team = %s' : '' ) .
	        ( $key == 'submission.probid' ? 'submission.probid = %s' : '' ) .
	        ( $key == 'submission.langid' ? 'submission.langid = %s' : '' ) .
	        ( $key == 'submission.submitid' ? 'submission.submitid = %s' : '' );
	       $cid, $value);
*/
	
	// Using MySQL < 4.0.4:

	$res = $DB->q('SELECT * FROM judging
	               LEFT JOIN submission ON (submission.submitid = judging.submitid)
	               WHERE judging.cid = %i AND valid = 1 AND
	               ( result IS NULL OR result != "correct" ) AND ' .
	               ( $key == 'judging.judgehost' ? 'judging.judgehost = %s' : '' ) .
	               ( $key == 'submission.team' ? 'submission.team = %s' : '' ) .
	               ( $key == 'submission.probid' ? 'submission.probid = %s' : '' ) .
	               ( $key == 'submission.langid' ? 'submission.langid = %s' : '' ) .
	               ( $key == 'submission.submitid' ? 'submission.submitid = %s' : '' )
				   ,
	              $cid, $value);

	while ( $jud = $res->next() ) {
		$DB->q('START TRANSACTION');
		
		$DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i',
		       $jud['judgingid']);

		$DB->q('UPDATE submission SET judgehost = NULL, judgemark = NULL
		        WHERE submitid = %i', $jud['submitid']);

		calcScoreRow($cid, $jud['team'], $jud['probid']);
		$DB->q('COMMIT');
	}
}
