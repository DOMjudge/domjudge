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

$table = @$_POST['table'];
$id    = @$_POST['id'];
$include_all = !empty($_POST['include_all']);

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
	$res = $DB->q('SELECT j.judgingid, s.submitid, s.teamid, s.probid, j.cid
	               FROM judging j
	               LEFT JOIN submission s USING (submitid)
	               WHERE j.cid IN (%Ai) AND j.valid = 1 AND ' .
	              ( $include_all ? '' :
	                'result != \'correct\' AND result IS NOT NULL AND ' ) .
	              $tablemap[$table] . ' = %s', $cids, $id);
}

if ( !$res || $res->count() == 0 ) {
	error("No judgings matched.");
}

while ( $jud = $res->next() ) {
	$DB->q('START TRANSACTION');

	$DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i',
	       $jud['judgingid']);

	$DB->q('UPDATE submission SET judgehost = NULL
	        WHERE submitid = %i', $jud['submitid']);

	// Prioritize single submission rejudgings
	if ( $table == 'submission' ) {
		$DB->q('UPDATE team SET judging_last_started = NULL
		        WHERE teamid IN (SELECT teamid FROM submission
		        WHERE submitid = %i)', $jud['submitid']);
	}

	calcScoreRow($jud['cid'], $jud['teamid'], $jud['probid']);
	$DB->q('COMMIT');

	auditlog('judging', $jud['judgingid'], 'mark invalid', '(rejudge)');
}


/** redirect back. */
header('Location: '.$table.'.php?id='.urlencode($id));
