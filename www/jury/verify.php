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
if (empty($id)) {
    error("No judging ID passed to mark as verified.");
}

$jury_member = $username;

$jdata = $DB->q('MAYBETUPLE SELECT j.result, s.submitid, s.cid, s.teamid, s.probid, s.langid
                 FROM judging j
                 LEFT JOIN submission s USING (submitid)
                 WHERE judgingid = %i', $id);

if (!$jdata) {
    error("Judging '$id' not found.");
}

$DB->q('START TRANSACTION');

// Explicitly unset jury_member when unmarking verified: otherwise this
// judging would be marked as "claimed".
$cnt = $DB->q('RETURNAFFECTED UPDATE judging
               SET verified = %i, jury_member = ' . ($val ? '%s ' : 'NULL %_ ') .
              ', verify_comment = %s WHERE judgingid = %i',
              $val, $jury_member, $comment, $id);
auditlog('judging', $id, $val ? 'set verified' : 'set unverified');

if ($cnt==1 && dbconfig_get('verification_required', 0)) {
    // log to event table (case of no verification required is handled
    // in the REST API function judging_runs_POST)
    eventlog('judging', $id, 'update', $jdata['cid']);
}

$DB->q('COMMIT');

if ($cnt == 0) {
    error("Judging was not modified.");
} elseif ($cnt > 1) {
    error("Validated more than one judging.");
}

if (dbconfig_get('verification_required', 0)) {
    calcScoreRow($jdata['cid'], $jdata['teamid'], $jdata['probid']);
    updateBalloons($jdata['submitid']);
}

/* redirect to referrer page after verification
 * or back to submission page when unverifying. */
if ($val) {
    $redirect = @$_POST['redirect'];
    if (empty($redirect)) {
        $redirect = 'submissions.php';
    }
    header('Location: '.$redirect);
} else {
    header('Location: submission.php?id=' .
           urlencode($jdata['submitid']) . '&jid=' . urlencode($id));
}
