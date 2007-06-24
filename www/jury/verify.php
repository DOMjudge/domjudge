<?php
/**
 * $Id$
 * Change the verification status of a given judging.
 */

require('init.php');
$id    = @$_POST['id'];
$val   = @$_POST['val'];
if ( empty($id) ) {
	error("No ID passed for to mark as verified.");
}

$jdata = $DB->q('TUPLE SELECT j.*, s.* FROM judging j
                 LEFT JOIN submission s USING (submitid)
                 WHERE judgingid = %i', $id);

if ( empty($jdata) ) {
	error("Judging not found.");
}

$verifier = "";
if ( ! empty($_POST['verifier_selected']) ) $verifier = $_POST['verifier_selected'];
if ( ! empty($_POST['verifier_typed'])    ) $verifier = $_POST['verifier_typed'];
	
$DB->q('UPDATE judging SET verified = %i, verifier = %s WHERE judgingid = %i',
       $val, $verifier, $id);
	
if ( VERIFICATION_REQUIRED ) {
	calcScoreRow($jdata['cid'], $jdata['teamid'], $jdata['probid']);

	// log to event table if successful (case of no verification
	// required is handled in judge/judgedaemon.php)
	if ( $jdata['result'] == 'correct' ) {
	$DB->q('INSERT INTO event (cid, teamid, langid, probid, submitid, description)
	        VALUES(%i, %i, %s, %s, %i, "problem solved")',
	       $jdata['cid'], $jdata['teamid'], $jdata['langid'],
		   $jdata['probid'], $jdata['submitid']);
	}
}

/** redirect back. */
header('Location: '.getBaseURI().'jury/submission.php?id=' . 
	urlencode($jdata['submitid']) . '&jid=' . urlencode($id));
