<?php
/**
 * Marks a set of submissions for rejudging, limited by key=value
 * key has to be a full quantifier, e.g. "submission.teamid"
 *
 * $key must be one of (judging.judgehost, submission.teamid, submission.probid,
 * submission.langid, submission.submitid)
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

/** These are the tables that we can deal with */
$tablemap = array (
	'contest'    => 's.cid',
	'judgehost'  => 'j.judgehost',
	'language'   => 's.langid',
	'problem'    => 's.probid',
	'submission' => 's.submitid',
	'team'       => 's.teamid'
	);

$table        = @$_POST['table'];
$id           = @$_POST['id'];
$reason       = @$_POST['reason'];
$include_all  = !empty($_POST['include_all']);
$full_rejudge = @$_POST['full_rejudge'];
if ( !isset($full_rejudge) ) {
	$full_rejudge = FALSE;
}

if ( empty($table) || empty($id) ) {
	error("no table or id passed for selection in rejudging");
} elseif ( !isset($tablemap[$table]) ) {
	error("unknown table in rejudging");
}

if ( !IS_ADMIN && $include_all ) {
	error("rejudging pending/correct submissions requires admin rights");
}

global $DB;

// This can be done in one Update from MySQL 4.0.4 and up, but that wouldn't
// allow us to call calcScoreRow() for the right rows, so we'll just loop
// over the results one at a time.

// Special case 'submission' for admin overrides
if ( IS_ADMIN && ($table == 'submission') ) $include_all = true;

$res = null;
$cids = getCurContests(FALSE);
if ( !empty($cids) ) {
	$restrictions = 'result != \'correct\' AND result IS NOT NULL AND ';
	if ( $include_all ) {
		if ( $full_rejudge ) {
			// do not include pending/queued submissions in rejudge
			$restrictions = 'result IS NOT NULL AND ';
		} else {
			$restrictions = '';
		}
	}
	$res = $DB->q('SELECT j.judgingid, s.submitid, s.teamid, s.probid, j.cid, s.rejudgingid
	               FROM judging j
	               LEFT JOIN submission s USING (submitid)
	               WHERE j.cid IN (%Ai) AND j.valid = 1 AND ' .
		      $restrictions .
	              $tablemap[$table] . ' = %s', $cids, $id);
}

if ( !$res || $res->count() == 0 ) {
	error("No judgings matched.");
}

if ( $full_rejudge ) {
	if ( empty($reason) ) {
		$reason = $table . ': ' . $id;
	}
	$rejudgingid = $DB->q('RETURNID INSERT INTO rejudging
	                       (userid_start, starttime, reason) VALUES (%i, %s, %s)',
	                      $userdata['userid'], now(), $reason);
}

while ( $jud = $res->next() ) {
	if ( isset($jud['rejudgingid']) ) {
		// already associated rejudging
		if ( $table == 'submission' ) {
			// clean up rejudging
			if ( $full_rejudge ) {
				$DB->q('DELETE FROM rejudging WHERE rejudgingid=%i', $rejudgingid);
			}
			error('submission is already part of rejudging r' . htmlspecialchars($jud['rejudgingid']));
		} else {
			// silently skip that submission
			continue;
		}
	}

	$DB->q('START TRANSACTION');

	if ( !$full_rejudge ) {
		$DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i',
		       $jud['judgingid']);
	}

	$DB->q('UPDATE submission SET judgehost = NULL' .
		( $full_rejudge ? ', rejudgingid=%i ' : '%_ ' ) .
		'WHERE submitid = %i AND rejudgingid IS NULL',
		@$rejudgingid, $jud['submitid']);

	// Prioritize single submission rejudgings
	if ( $table == 'submission' ) {
		$DB->q('UPDATE team SET judging_last_started = NULL
		        WHERE teamid IN (SELECT teamid FROM submission
		        WHERE submitid = %i)', $jud['submitid']);
	}

	if ( !$full_rejudge ) {
		calcScoreRow($jud['cid'], $jud['teamid'], $jud['probid']);
	}
	$DB->q('COMMIT');

	if ( !$full_rejudge ) {
		auditlog('judging', $jud['judgingid'], 'mark invalid', '(rejudge)');
	}
}


/** redirect back. */
if ( $full_rejudge ) {
	header('Location: rejudging.php?id='.urlencode($rejudgingid));
} else {
	header('Location: '.$table.'.php?id='.urlencode($id));
}
