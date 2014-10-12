<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show clarification request box.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();

if ( isset($id) ) {

	$req = $DB->q('MAYBETUPLE SELECT * FROM clarification
	               WHERE cid = %i AND clarid = %i', $cid, $id);
	if ( ! $req ) error("clarification $id not found");
	if ( ! canViewClarification($contestid, $req) ) {
		error("Permission denied");
	}
	$myrequest = ( $req['sender'] == $contestid );

	$respid = empty($req['respid']) ? $id : $req['respid'];
}

// insert a request (if posted)
if ( isset($_POST['submit']) && !empty($_POST['bodytext']) ) {
	// Disallow problems that are not submittable or
	// before contest start.
	if ( !problemVisible($_POST['problem']) ) $_POST['problem'] = 'general';

	$newid = $DB->q('RETURNID INSERT INTO clarification
	                 (cid, submittime, sender, probid, body)
	                 VALUES (%i, %s, %i, %i, %s)',
			$cid, now(), $contestid,
	                ($_POST['problem'] == 'general' ? NULL : $_POST['problem']),
	                $_POST['bodytext']);

	auditlog('clarification', $newid, 'added', null, null, $cid);

	// redirect back to the original location
	header('Location: ./');
	exit;
}

$title = 'Clarifications';
require(LIBWWWDIR . '/header.php');

if ( isset($id) ) {
	// display clarification thread
	if ( $myrequest ) {
		echo "<h1>Clarification Request</h1>\n\n";
	} else {
		echo "<h1>Clarification</h1>\n\n";
	}
	putClarification($respid, $contestid);

	echo "<h2>Send Clarification Request</h2>\n\n";
	putClarificationForm("clarification.php", $id, $cid);
} else {
	// display a clarification request send box
	echo "<h1>Send Clarification Request</h1>\n\n";
	putClarificationForm("clarification.php", null, $cid);
}

require(LIBWWWDIR . '/footer.php');
