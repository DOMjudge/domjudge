<?php
/**
 * Change the valid status of a given submission.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( ! IS_ADMIN ) {
	error("Admin privileges are required for this operation.");
}

$id    = @$_POST['id'];
$val   = @$_POST['val'];
if ( empty($id) ) {
	error("No ID passed for to mark as invalid.");
}

$cnt = $DB->q('RETURNAFFECTED UPDATE submission s
               SET s.valid = %i WHERE s.submitid = %i',
              $val, $id);

if ( $cnt == 0 ) {
	error("Submission not found.");
} else if ( $cnt > 1 ) {
	error("Ignored more than one judging.");
}

$sdata = $DB->q('TUPLE SELECT submitid, cid, teamid, probid
                 FROM submission
                 WHERE submitid = %i', $id);

calcScoreRow($sdata['cid'], $sdata['teamid'], $sdata['probid']);

/* redirect back. */
header('Location: '.getBaseURI().'jury/submission.php?id=' .
	urlencode($sdata['submitid']));
