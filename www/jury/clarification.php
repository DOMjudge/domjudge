<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show general clarification box.
 *
 * $Id$
 */

require('init.php');
$title = 'Clarifications';

$cid = getCurContest();

if ( isset($_REQUEST['id']) ) {
	$id = (int)$_REQUEST['id'];
	if ( ! $id ) error("Missing clarification id");

	$req = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name FROM clarification q
		LEFT JOIN team t ON (t.login = q.sender)
		WHERE q.cid = %i AND q.clarid = %i', $cid, $id);
	
	if ( ! $req ) error("clarification $id not found");

	$respid = (int) (empty($req['respid']) ? $id : $req['respid']);
	$isgeneral = FALSE;
} else {
	$isgeneral = TRUE;
}

// insert a new response (if posted)
if ( isset($_REQUEST['submit'])	&& !empty($_REQUEST['bodytext']) ) {
	if ( $isgeneral ) {
		$newid = $DB->q('RETURNID INSERT INTO clarification
			(cid, submittime, recipient, body)
			VALUES (%i, now(), %s, %s)',
			$cid, ( empty($_REQUEST['sendto']) ? NULL : $_REQUEST['sendto'] ),
			$_REQUEST['bodytext']);
	} else {
		$newid = $DB->q('RETURNID INSERT INTO clarification
			(cid, respid, submittime, recipient, body)
			VALUES (%i, %i, now(), %s, %s)',
			$cid, $respid,
			( empty($_REQUEST['sendto']) ? NULL : $_REQUEST['sendto'] ),
			$_REQUEST['bodytext']);
	}
	if ( ! $isgeneral ) {
		$DB->q('UPDATE clarification SET answered = 1
			WHERE clarid = %i', $respid);
	}

	// redirect back to the original location
	if ( $isgeneral ) {
		header('Location: ' . getBaseURI() . 'jury/clarifications.php');
	} else {
		header('Location: ' . getBaseURI() . 'jury/clarification.php?id=' . $id);
	}
	exit;
}

// (un)set 'answered' (if posted)
if ( isset($_REQUEST['submit']) && $_REQUEST['answered']!=NULL ) {
	$DB->q('UPDATE clarification SET answered = %i WHERE clarid = %i',
		(int)$_REQUEST['answered'], $respid);

	// redirect back to the original location
	header('Location: ' . getBaseURI() . 'jury/clarification.php?id=' . $id);
	exit;
}

require('../header.php');
require('menu.php');
require('../clarification.php');

if ( ! $isgeneral ) {

// display clarification thread
echo "<h1>Clarification $id</h1>\n\n";

if ( ! empty ( $req['respid'] ) ) {
	$orig = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name
		FROM clarification q LEFT JOIN team t ON (t.login = q.sender)
		WHERE q.clarid = %i', $respid);
	echo '<p>See the <a href="clarification.php?id=' . $respid .
		'">original clarification ' . $respid . '</a> by ' .
		( $orig['sender']==NULL ? 'Jury' : 
			'<a href="team.php?id=' . urlencode($orig['sender']) . '">' .
			htmlspecialchars($orig['sender'] . ': ' . $orig['name']) .
			'</a>' ) .
		"</p>\n\n";

}

putClarification($id, NULL, TRUE);

// Display button to (un)set request as 'answered'
// Not relevant for 'general clarifications', ie those with sender=null
if ( !empty($req['sender']) ) {
	echo '<form action="clarification.php" method="post"><p>' . "\n";
	echo '<input type="hidden" name="id" value="' . $id . "\" />\n";
	echo '<input type="hidden" name="answered" value="' .
		($req['answered'] ? '0' : '1') . "\" />\n";
	echo '<input type="submit" name="submit" value="Set ' .
		($req['answered'] ? 'unanswered' : 'answered') . "\" />\n";
	echo "</p></form>\n";
}

} // end if ( ! $isgeneral )


// display a clarification send box
if ( $isgeneral ) {
	echo "<h1>Send Clarification</h1>\n\n";
	putClarificationForm("clarification.php", TRUE);
} else {
	echo "<h1>Send Response</h1>\n\n";
	putClarificationForm("clarification.php", TRUE, $respid);
}

require('../footer.php');
