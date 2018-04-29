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

$id = getRequestID();

if ( isset($id) ) {

	if ( empty($cids) ) {
		$req = null;
	} else {
		$req = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name FROM clarification q
		               LEFT JOIN team t ON (t.teamid = q.sender)
		               WHERE q.cid IN (%Ai) AND q.clarid = %i', $cids, $id);
	}

	if ( ! $req ) error("clarification $id not found, cids = " . implode(', ', $cids));

	$respid = (int) (empty($req['respid']) ? $id : $req['respid']);
	$isgeneral = FALSE;
} else {
	$respid = NULL;
	$isgeneral = TRUE;
}

$jury_member = $username;

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

	if ( empty($_POST['sendto']) ) {
		$sendto = null;
	} elseif ( $_POST['sendto'] == 'domjudge-must-select' ) {
		error ( 'You must select somewhere to send the clarification to.' );
	} else {
		$sendto = $_POST['sendto'];
	}

	list($cid, $probid) = explode('-', $_POST['problem']);
	$category = NULL;
	$queue = NULL;
	if ($respid !== NULL) {
		$queue = $DB->q('MAYBEVALUE SELECT queue FROM clarification WHERE clarid = %i', $respid);
	}
	if ( !ctype_digit($probid) ) {
		$category = $probid;
		$probid = NULL;
	} elseif ( $queue===NULL && $respid===NULL ) {
		$queue = dbconfig_get('clar_default_problem_queue');
		if ($queue === "") {
			$queue = null;
		}
	}

	// If database supports it, wrap this in a transaction so we
	// either send the clarification AND mark it unread for everyone,
	// or we don't. If no transaction support, we just have to hope
	// this goes well.
	$DB->q('START TRANSACTION');

	$newid = $DB->q('RETURNID INSERT INTO clarification
	                 (cid, respid, submittime, recipient, probid, category, queue, body,
	                  answered, jury_member)
	                 VALUES (%i, ' .
	                ($respid===NULL ? 'NULL %_' : '%i') . ', %s, %s, %i, %s, %s, %s, %i, ' .
	                (isset($jury_member) ? '%s)' : 'NULL %_)'),
	                $cid, $respid, now(), $sendto, $probid, $category, $queue,
	                $_POST['bodytext'], 1, $jury_member);

	if ( ! $isgeneral ) {
		$DB->q('UPDATE clarification SET answered = 1, jury_member = ' .
		       (isset($jury_member) ? '%s' : 'NULL %_') . ' WHERE clarid = %i',
		       $jury_member, $respid);
	}

	$DB->q('COMMIT');

	eventlog('clarification', $newid, 'create', $cid);
	auditlog('clarification', $newid, 'added', null, null, $cid);

	if( is_null($sendto) ) {
		// mark the messages as unread for the team(s)
		$teams = $DB->q('COLUMN SELECT teamid FROM team');
		foreach($teams as $teamid) {
			$DB->q('INSERT INTO team_unread (mesgid, teamid)
			        VALUES (%i, %i)', $newid, $teamid);
		}
	} else {
		$DB->q('INSERT INTO team_unread (mesgid, teamid)
		        VALUES (%i, %i)', $newid, $sendto);
	}

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

if (isset($_POST['subject'])) {
	list($cid, $probid) = explode('-', $_POST['subject']);
	$category = NULL;
	if ( !ctype_digit($probid) ) {
		$category = $probid;
		$probid = NULL;
	}

	$DB->q('UPDATE clarification SET cid = %i, category = %s, probid = %i WHERE clarid = %i', $cid, $category, $probid, $id);
}

if (isset($_POST['queue'])) {
	$DB->q('UPDATE clarification SET queue = %s WHERE clarid = %i', $_POST['queue'], $id);
	auditlog('clarification', $id, 'queue changed');
}

require_once(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/clarification.php');

if ( ! $isgeneral ) {

// display clarification thread
echo "<h1>Clarification $id</h1>\n\n";

if ( !$req['answered'] ) {
	echo addForm($pagename . '?id=' . urlencode($id));

	echo "<p>Claimed: " .
	    "<strong>" . printyn(!empty($req['jury_member'])) . "</strong>";
	if ( empty($req['jury_member']) ) {
		echo '; ';
	} else {
		echo ', by ' . specialchars($req['jury_member']) . '; ' .
		    addSubmit('unclaim', 'unclaim') . ' or ';
	}
	echo addSubmit('claim', 'claim') . '</p>' .
	    addEndForm();
}

if ( ! empty ( $req['respid'] ) ) {
	$orig = $DB->q('MAYBETUPLE SELECT q.*, t.name AS name FROM clarification q
	                LEFT JOIN team t ON (t.teamid = q.sender)
	                WHERE q.clarid = %i', $respid);
	echo '<p>See the <a href="clarification.php?id=' . $respid .
		'">original clarification ' . $respid . '</a> by ' .
		( $orig['sender']==NULL ? 'Jury' :
			'<a href="team.php?id=' . urlencode($orig['sender']) . '">' .
			specialchars($orig['name'] . " (t" . $orig['sender'] . ")") .
			'</a>' ) .
		"</p>\n\n";

}

putClarification($id, NULL);

// Display button to (un)set request as 'answered'
// Not relevant for 'general clarifications', ie those with sender=null
if ( !empty($req['sender']) ) {
	echo addForm($pagename) .
		addHidden('id', $id) .
		addHidden('answered', !$req['answered']) .
		addSubmit('Set ' . ($req['answered'] ? 'unanswered' : 'answered'), 'answer') .
		addEndForm();
}

} // end if ( ! $isgeneral )


// display a clarification send box
if ( $isgeneral ) {
	echo "<h1>Send Clarification</h1>\n\n";
	putClarificationForm("clarification.php");
} else {
	echo "<h1>Send Response</h1>\n\n";
	putClarificationForm("clarification.php", $respid);
}

?>
<script type="text/javascript">
	$(function() {
		$(['subject', 'queue']).each(function(_, field) {
			$('.clarification-' + field + '-change-button').on('click', function () {
				$(this).closest('.clarification-' + field).hide();
				$(this).closest('td').find('.clarification-' + field + '-form').show();
			});
			$('.clarification-' + field + '-cancel-button').on('click', function () {
				$(this).closest('.clarification-' + field + '-form').hide();
				$(this).closest('td').find('.clarification-' + field).show();
			});
			$('.clarification-' + field + '-form select').on('change', function () {
				var $select = $(this);
				var $form = $('.clarification-' + field + '-form');
				var clarId = $form.data('clarification-id');
				var value = $select.find(':selected').text();
				if (confirm('Are you sure you want to change the ' + field + ' of clarification ' + clarId + ' to "' + value + '"?')) {
					$form.find('form').submit();
				} else {
					$select.val($form.data('current-selected-' + field));
				}
			});
		});
	});
</script>
<?php

require(LIBWWWDIR . '/footer.php');
