<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show clarification request box.
 *
 * $Id$
 */

require('init.php');

$cid = getCurContest();

if ( isset($_REQUEST['id']) ) {
	$id = (int)$_REQUEST['id'];
	if ( ! $id ) error("Missing clarification id");

	$req = $DB->q('MAYBETUPLE SELECT * FROM clarification
		WHERE cid = %i AND clarid = %i', $cid, $id);
	if ( ! $req ) error("clarification $id not found");
	if ( ! ($req['sender']==$login || ($req['sender']==NULL &&
		($req['recipient']==NULL || $req['recipient']==$login)) ) ) {
		error("Permission denied");
	}

	$respid = empty($req['respid']) ? $id : $req['respid'];
}

// insert a request (if posted)
if ( isset($_REQUEST['submit'])	&& !empty($_REQUEST['bodytext']) ) {
	$newid = $DB->q('RETURNID INSERT INTO clarification
		(cid, submittime, sender, body)
		VALUES (%i, now(), %s, %s)',
		$cid, $login, $_REQUEST['bodytext']);

	// redirect back to the original location
	header('Location: '.getBaseURI().'team/clarifications.php');
	exit;
}

$title = 'Clarifications';
require('../header.php');
require('../clarification.php');

if ( isset($id) ) {
	// display clarification thread
	echo "<h1>Clarification $id</h1>\n\n";
	
	putClarification($respid, $login);
} else {
	// display a clarification request send box
	echo "<h1>Send Clarification Request</h1>\n\n";
	putClarificationForm("clarification.php");
}

include('../footer.php');
