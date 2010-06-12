<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show general clarification box.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Clarifications';

if ( isset($_REQUEST['id']) ) {
	$id = (int)$_REQUEST['id'];
	if ( ! $id ) error("Missing clarification id");

	$req = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name FROM clarification q
	               LEFT JOIN team t ON (t.login = q.sender)
	               WHERE q.cid = %i AND q.clarid = %i', $cid, $id);

	if ( ! $req ) error("clarification $id not found, cid = $cid");

	$respid = (int) (empty($req['respid']) ? $id : $req['respid']);
	$isgeneral = FALSE;
} else {
	$isgeneral = TRUE;
}

// insert a new response (if posted)
if ( isset($_POST['submit']) && !empty($_POST['bodytext']) ) {

	// If database supports it, wrap this in a transaction so we
	// either send the clarification AND mark it unread for everyone,
	// or we don't. If no transaction support, we just have to hope
	// this goes well.
	$DB->q('START TRANSACTION');

	if ( empty($_POST['sendto']) ) {
		$sendto = null;
	} elseif ( $_POST['sendto'] == 'domjudge-must-select' ) {
		error ( 'You must select somewhere to send the clarification to.' );
	} else {
		$sendto = $_POST['sendto'];
	}

	if ( $isgeneral ) {
		$newid = $DB->q('RETURNID INSERT INTO clarification
		                 (cid, submittime, recipient, probid, body)
		                 VALUES (%i, %s, %s, %s, %s)',
		                $cid, now(), $sendto,
		                ($_POST['problem'] == 'general' ? NULL : $_POST['problem']),
		                $_POST['bodytext']);
	} else {
		$newid = $DB->q('RETURNID INSERT INTO clarification
		                 (cid, respid, submittime, recipient, probid, body)
		                 VALUES (%i, %i, %s, %s, %s, %s)',
		                $cid, $respid, now(), $sendto,
		                ($_POST['problem'] == 'general' ? NULL : $_POST['problem']),
		                $_POST['bodytext']);
	}
	if ( ! $isgeneral ) {
		$DB->q('UPDATE clarification SET answered = 1 WHERE clarid = %i', $respid);
	}

	if( is_null($sendto) ) {
		// log to event table if clarification to all teams
		$DB->q('INSERT INTO event (eventtime, cid, clarid, description)
		        VALUES(%s, %i, %i, "clarification")', now(), $cid, $newid);

		// mark the messages as unread for the team(s)
		$teams = $DB->q('COLUMN SELECT login FROM team');
		foreach($teams as $login) {
			$DB->q('INSERT INTO team_unread (mesgid, type, teamid)
			        VALUES (%i, "clarification", %s)', $newid, $login);
		}
	} else {
		$DB->q('INSERT INTO team_unread (mesgid, type, teamid)
		        VALUES (%i, "clarification", %s)', $newid, $sendto);
	}

	$DB->q('COMMIT');

	// redirect back to the original location
	if ( $isgeneral ) {
		header('Location: clarifications.php');
	} else {
		header('Location: clarification.php?id=' . $id);
	}
	exit;
}

// (un)set 'answered' (if posted)
if ( isset($_POST['submit']) && isset($_POST['answered']) ) {
	$DB->q('UPDATE clarification SET answered = %i WHERE clarid = %i',
	       (int)$_POST['answered'], $respid);

	// redirect back to the original location
	header('Location: clarification.php?id=' . $id);
	exit;
}

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/clarification.php');

if ( ! $isgeneral ) {

// display clarification thread
echo "<h1>Clarification $id</h1>\n\n";

if ( ! empty ( $req['respid'] ) ) {
	$orig = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name FROM clarification q
	                LEFT JOIN team t ON (t.login = q.sender)
	                WHERE q.clarid = %i', $respid);
	echo '<p>See the <a href="clarification.php?id=' . $respid .
		'">original clarification ' . $respid . '</a> by ' .
		( $orig['sender']==NULL ? 'Jury' :
			'<a href="team.php?id=' . urlencode($orig['sender']) . '">' .
			htmlspecialchars($orig['sender'] . ': ' . $orig['name']) .
			'</a>' ) .
		"</p>\n\n";

}

putClarification($id, NULL);

// Display button to (un)set request as 'answered'
// Not relevant for 'general clarifications', ie those with sender=null
if ( !empty($req['sender']) ) {
	require_once(LIBWWWDIR . '/forms.php');

	echo addForm('clarification.php') .
		addHidden('id', $id) .
		addHidden('answered', !$req['answered']) .
		addSubmit('Set ' . ($req['answered'] ? 'unanswered' : 'answered'), 'submit') .
		addEndForm();
}

} // end if ( ! $isgeneral )


// display a clarification send box
if ( $isgeneral ) {
	echo "<h1>Send Clarification</h1>\n\n";
	putClarificationForm("clarification.php", $cdata['cid']);
} else {
	echo "<h1>Send Response</h1>\n\n";
	putClarificationForm("clarification.php", $cdata['cid'], $respid);
}

require(LIBWWWDIR . '/footer.php');
