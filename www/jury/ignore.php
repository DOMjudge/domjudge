<?php
/**
 * Change the valid status of a given submission.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( ! IS_ADMIN ) {
	error("Admin privileges are required for this operation.");
}

$id  = @$_POST['id'];
$val = @$_POST['val'];
if ( empty($id) ) {
	error("No submission ID passed to mark as (in)valid.");
}

$cnt = $DB->q('RETURNAFFECTED UPDATE submission s
               SET s.valid = %i WHERE s.submitid = %i',
              $val, $id);

auditlog('submission', $id, 'marked ' . ($val?'valid':'invalid'));

if ( $cnt == 0 ) {
	error("Submission s$id not found.");
} else if ( $cnt > 1 ) {
	error("Ignored more than one submission.");
}

$sdata = $DB->q('TUPLE SELECT submitid, cid, teamid, probid
                 FROM submission
                 WHERE submitid = %i', $id);

calcScoreRow($sdata['cid'], $sdata['teamid'], $sdata['probid']);

/* redirect back. */
header('Location: submission.php?id=' .
	urlencode($sdata['submitid']));
