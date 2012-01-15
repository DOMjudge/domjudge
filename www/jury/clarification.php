<?php
/**
 * Show clarification thread and reply box.
 * When no id is given, show general clarification box.
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
	$respid = NULL;
	$isgeneral = TRUE;
}

$jury_member = getJuryMember();

if ( isset($_REQUEST['claim']) || isset($_REQUEST['unclaim']) ) {

	// Send headers now: after cookies, before possible warning messages.
	if ( !isset($_REQUEST['unclaim']) ) require_once(LIBWWWDIR . '/header.php');

	if ( $req['answered'] ) {
		warning("Cannot claim this clarification: clarification already answered.");
	} else if ( empty($jury_member) && isset($_REQUEST['claim']) ) {
		warning("Cannot claim this clarification: no jury_member found.");
	} else {
		if ( !empty($req['jury_member']) && isset($_REQUEST['claim']) ) {
			warning("Submission claimed and previous owner " .
			        @$req['jury_member'] . " replaced.");
		}
		$req['jury_member'] = $jury_member;
		$DB->q('UPDATE clarification SET jury_member = ' .
		       (isset($_REQUEST['unclaim']) ? 'NULL %_ ' : '%s ') .
		       'WHERE clarid = %i', $jury_member, $id);

		if ( isset($_REQUEST['unclaim']) ) header('Location: clarifications.php');
	}
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

	$newid = $DB->q('RETURNID INSERT INTO clarification
	                 (cid, respid, submittime, recipient, probid, body,
 	                  answered, jury_member)
	                 VALUES (%i, ' .
	                ($respid===NULL ? 'NULL %_' : '%i') . ', %s, %s, %s, %s, %i, ' .
	                (isset($jury_member) ? '%s)' : 'NULL %_)'),
	                $cid, $respid, now(), $sendto,
	                ($_POST['problem'] == 'general' ? NULL : $_POST['problem']),
	                $_POST['bodytext'], 1, $jury_member);
	auditlog('clarification', $newid, 'added');

	if ( ! $isgeneral ) {
		$DB->q('UPDATE clarification SET answered = 1, jury_member = ' .
		       (isset($jury_member) ? '%s' : 'NULL %_') . ' WHERE clarid = %i',
		       $jury_member, $respid);
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
if ( isset($_POST['answer']) && isset($_POST['answered']) ) {
	$answered = (int)$_POST['answered'];
	$DB->q('UPDATE clarification SET answered = %i, jury_member = ' .
	       ($answered ? '%s ' : 'NULL %_ ') . 'WHERE clarid = %i',
	       $answered, $jury_member, $respid);

	auditlog('clarification', $respid, 'marked ' . ($answered?'answered':'unanswered'));

	// redirect back to the original location
	header('Location: clarification.php?id=' . $id);
	exit;
}

require_once(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/clarification.php');

if ( ! $isgeneral ) {

// display clarification thread
echo "<h1>Clarification $id</h1>\n\n";

$pagename = basename($_SERVER['PHP_SELF']);
if ( !$req['answered'] ) {
	echo addForm($pagename . '?id=' . urlencode($id));

	echo "<p>Claimed: " .
	    "<strong>" . printyn(!empty($req['jury_member'])) . "</strong>";
	if ( empty($req['jury_member']) ) {
		echo '; ';
	} else {
		echo ', by ' . htmlspecialchars($req['jury_member']) . '; ' .
		    addSubmit('unclaim', 'unclaim') . ' or ';
	}
	echo addSubmit('claim', 'claim') . '</p>' .
	    addEndForm();
}

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
	echo addForm('clarification.php') .
		addHidden('id', $id) .
		addHidden('answered', !$req['answered']) .
		addSubmit('Set ' . ($req['answered'] ? 'unanswered' : 'answered'), 'answer') .
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
