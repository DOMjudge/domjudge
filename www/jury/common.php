<?php

/**
 * Common functions in jury interface
 *
 * $Id: common.php 696 2005-01-21 20:42:36Z nkp0405 $
 */


/**
 * Outputs a list of judgings, limited by key=value
 */
function putJudgings($key, $value) {
	global $DB;

	// get the judgings for a specific key and value pair
	$res = $DB->q('SELECT * FROM judging
	               WHERE '.$key.' = %s AND cid = %i ORDER BY starttime DESC',
	               $value, getCurContest() );

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table>\n<tr><th>ID</th><th>start</th><th>end</th>";
		if ( $key != 'judge' ) echo "<th>judge</th>";
		echo "<th>result</th><th>valid</th></tr>\n";
		while( $jud = $res->next() ) {
			echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] .
				'">j' .	(int)$jud['judgingid'] . '</a></td>';
			echo '<td>' . printtime($jud['starttime']) . '</td>';
			echo '<td>' . printtime(@$jud['endtime'])  . '</td>';
			echo '<td><a href="judger.php?id=' . urlencode(@$jud['judgerid']) .
				'">' . printhost(@$jud['judgerid']) . '</a></td>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] . '">' .
				printresult(@$jud['result'], $jud['valid']) . '</a></td>';
			echo '<td align="center">' . printyn($jud['valid']) . '</td>';
			echo "</tr>\n";
		}
		echo "</table>\n\n";
	}

	return;
}

/**
 * Marks a set of submissions for rejudging, limited by key=value
 */
function rejudge($key, $value) {
	global $DB;

	$cid = getCurContest();

	// Using MySQL >= 4.0.4:
/*
	$DB->q('UPDATE judging j
	        LEFT JOIN submission s ON (s.submitid=j.submitid)
	        SET j.valid = 0, s.judgerid = NULL, s.judgermark = NULL
	        WHERE s.'.$key.' = %s AND cid = %i AND valid = 1 AND
	        ( result IS NULL OR result != "correct" )',
	       $value, $cid);
*/
	
	// Using MySQL < 4.0.4:

	$res = $DB->q('SELECT j.*,s.submitid,s.team,s.probid,s.langid FROM judging j
	               LEFT JOIN submission s ON (s.submitid=j.submitid)
	               WHERE s.'.$key.' = %s AND j.cid = %i AND valid = 1 AND
	               ( result IS NULL OR result != "correct" )',
	              $value, $cid);

	while ( $jud = $res->next() ) {
		// START TRANSACTION
		$DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i',
		       $jud['judgingid']);

		$DB->q('UPDATE submission SET judgerid = NULL, judgemark = NULL
		        WHERE submitid = %i', $jud['submitid']);

		calcScoreRow($cid, $jud['team'], $jud['probid']);
		// END TRANSACTION
	}
}
