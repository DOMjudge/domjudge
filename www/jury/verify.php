<?php
/**
 * Change the verification status of a given judging.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$id      = @$_POST['id'];
$val     = @$_POST['val'];
$comment = @$_POST['comment'];
if ( empty($id) ) error("No judging ID passed to mark as verified.");

$jury_member = $username;

// Explicitly unset jury_member when unmarking verified: otherwise this
// judging would be marked as "claimed".
$cnt = $DB->q('RETURNAFFECTED UPDATE judging
               SET verified = %i, jury_member = ' . ($val ? '%s ' : 'NULL %_ ') .
              ', verify_comment = %s WHERE judgingid = %i',
              $val, $jury_member, $comment, $id);
auditlog('judging', $id, $val ? 'set verified' : 'set unverified');

if ( $cnt == 0 ) {
	error("Judging '$id' not found or nothing changed.");
} else if ( $cnt > 1 ) {
	error("Validated more than one judging.");
}

$jdata = $DB->q('TUPLE SELECT j.result, s.submitid, s.cid, s.teamid, s.probid, s.langid
                 FROM judging j
                 LEFT JOIN submission s USING (submitid)
                 WHERE judgingid = %i', $id);

if ( dbconfig_get('verification_required', 0) ) {
	calcScoreRow($jdata['cid'], $jdata['teamid'], $jdata['probid']);

	// log to event table (case of no verification required is handled
	// in the REST API function judging_runs_POST)
	$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid, submitid, description)
	        VALUES (%s, %i, %s, %s, %s, %i, "problem judged")',
	       now(), $jdata['cid'], $jdata['teamid'], $jdata['langid'],
	       $jdata['probid'], $jdata['submitid']);

	if ( $jdata['result'] == 'correct' ) {
		$balloons_enabled = (bool)$DB->q("VALUE SELECT process_balloons FROM contest WHERE cid = %i", $jdata['cid']);
		if ( $balloons_enabled ) {
			$DB->q('INSERT INTO balloon (submitid) VALUES(%i)',
			       $jdata['submitid']);
		}
	}
}

/* redirect to referrer page after verification
 * or back to submission page when unverifying. */
if ( $val ) {
	$redirect = @$_POST['redirect'];
	if ( empty($redirect) ) $redirect = 'submissions.php';
	header('Location: '.$redirect);
} else {
	header('Location: submission.php?id=' .
	       urlencode($jdata['submitid']) . '&jid=' . urlencode($id));
}
