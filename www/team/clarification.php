<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show clarification request box.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

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
	$myrequest = ( $req['sender'] == $login );

	$respid = empty($req['respid']) ? $id : $req['respid'];
}

// insert a request (if posted)
if ( isset($_POST['submit']) && !empty($_POST['bodytext']) ) {
	$newid = $DB->q('RETURNID INSERT INTO clarification
	                 (cid, submittime, sender, body)
	                 VALUES (%i, %s, %s, %s)',
	                 $cid, now(), $login, $_POST['bodytext']);

	// redirect back to the original location
	header('Location: '.getBaseURI().'team/clarifications.php');
	exit;
}

$title = 'Clarifications';
require('../header.php');
require('../clarification.php');

if ( isset($id) ) {
	// display clarification thread
	if ( $myrequest ) {
		echo "<h1>Clarification Request</h1>\n\n";
	} else {
		echo "<h1>Clarification</h1>\n\n";
	}
	putClarification($respid, $login);
	
	echo "<h2>Send Clarification Request</h2>\n\n";
	putClarificationForm("clarification.php", false, $id);
} else {
	// display a clarification request send box
	echo "<h1>Send Clarification Request</h1>\n\n";
	putClarificationForm("clarification.php");
}

include('../footer.php');
