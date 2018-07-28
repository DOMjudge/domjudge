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

$table        = @$_POST['table'];
$id           = @$_POST['id'];
$reason       = @$_POST['reason'];
$include_all  = !empty($_POST['include_all']);
$full_rejudge = @$_POST['full_rejudge'];
if (!isset($full_rejudge)) {
    $full_rejudge = false;
}

if (empty($table) || empty($id)) {
    error("no table or id passed for selection in rejudging");
}

if (empty($reason)) {
    $reason = $table . ': ' . $id;
}

if (!IS_ADMIN && $include_all) {
    error("rejudging pending/correct submissions requires admin rights");
}

// Special case 'submission' for admin overrides
if (IS_ADMIN && ($table == 'submission')) {
    $include_all = true;
}

$rejudgingid = rejudge(
    $table,
    $id,
    $include_all,
    $full_rejudge,
                       $reason,
    $userdata['userid']
);

/** redirect back. */
if ($full_rejudge) {
    header('Location: rejudging.php?id='.urlencode($rejudgingid));
} else {
    header('Location: '.$table.'.php?id='.urlencode($id));
}
