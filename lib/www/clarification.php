<?php
/**
 * Clarification helper functions for jury and teams
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once(LIBDIR . '/lib.misc.php');

/**
 * Marks a given clarification as viewed by a specific team,
 * so it doesn't show up as "unread" anymore in their interface.
 */
function setClarificationViewed($clar, $team)
{
	global $DB;
	$DB->q('DELETE FROM team_unread
	        WHERE mesgid = %i AND type = "clarification" AND teamid = %s',
	       $clar, $team);
}

/**
 * Returns wether a team is allowed to view a clarification.
 */
function canViewClarification($team, $clar)
{
	return (
		   $clar['sender'] == $team
		|| $clar['recipient'] == $team
		|| ($clar['sender'] == NULL && $clar['recipient'] == NULL)
		);
}

/**
 * Output a single clarification.
 * Helperfunction for putClarification, do _not_ use directly!
 */
function putClar($clar, $isjury = false)
{
	if ( $clar['sender'] ) {
		$from = '<span class="teamid">' . htmlspecialchars($clar['sender']) .
			'</span>: ' . htmlspecialchars($clar['fromname']);
	} else {
		$from = 'Jury';
	}
	if ( $clar['recipient'] && $from == 'Jury' ) {
		$to = '<span class="teamid">' . htmlspecialchars($clar['recipient']) .
			'</span>: ' . htmlspecialchars($clar['toname']);
	} else {
		$to = ( $from == 'Jury' ) ? 'All' : 'Jury' ;
	}

	echo "<table>\n";

	echo '<tr><td scope="row">From:</td><td>';
	echo make_link($from, "team.php?id=" . urlencode($clar['sender']), $isjury && $clar['sender'], TRUE);
	echo "</td></tr>\n";

	echo '<tr><td scope="row">To:</td><td>';
	echo make_link($to, "team.php?id=" . urlencode($clar['recipient']), $isjury && $clar['recipient'], TRUE);
	echo "</td></tr>\n";

	echo '<tr><td scope="row">Time:</td><td>';
	echo printtime($clar['submittime']);
	echo "</td></tr>\n";

	echo '<tr><td valign="top"></td><td class="filename">';
	echo '<pre class="output_text">' . wordwrap(htmlspecialchars($clar['body']),80) . "</pre>";
	echo "</td></tr>\n";

	echo "</table>\n";

	return;
}

/**
 * Output a clarification (and thread) for id $id.
 */
function putClarification($id,  $team = NULL, $isjury = FALSE)
{
	if ( $team==NULL && ! $isjury ) {
		error("access denied to clarifications: you seem to be team nor jury");
	}

	global $DB;

	$clar = $DB->q('TUPLE SELECT * FROM clarification WHERE clarid = %i', $id);
	
	$clarifications = $DB->q('SELECT c.*, t.name AS toname, f.name AS fromname
		FROM clarification c
		LEFT JOIN team t ON (t.login = c.recipient)
		LEFT JOIN team f ON (f.login = c.sender)
		WHERE c.respid = %i OR c.clarid = %i
		ORDER BY c.submittime, c.clarid',
		$clar['clarid'], $clar['clarid']);

	while ( $clar = $clarifications->next() ) {
		// check permission to view this clarification
		if ($isjury || canViewClarification($team, $clar)) {
			setClarificationViewed($clar['clarid'], $team);
			putClar($clar, $isjury);
			echo "<br />\n\n";
		}
	}
}

/**
 * Summarize a clarification.
 * Helper function for putClarificationList.
 */
function summarizeClarification($body)
{
	// when making a summary, try to igonore the quoted text
	$split = explode("\n", $body);
	$newbody = '';
	foreach($split as $line) {
		if ( strlen($line) > 0 && $line{0} != '>' ) $newbody .= $line;
	}
	return htmlspecialchars( str_cut( ( empty($newbody) ? $body : $newbody ), 80) );
}

/**
 * Print a list of clarifications in a table with links to the clarifications.
 */
function putClarificationList($clars, $team = NULL, $isjury = FALSE)
{
	if ( $team==NULL && ! $isjury ) {
		error("access denied to clarifications: you seem to be team nor jury");
	}

	echo "<table class=\"list\">\n<thead>\n";
	echo "<tr><th scope=\"col\">ID</th><th scope=\"col\">from</th>" .
	     "<th scope=\"col\">to</th><th scope=\"col\">time</th>" .
	     "<th scope=\"col\">text</th></tr>\n</thead>\n<tbody>\n";

	while ( $clar = $clars->next() ) {
		// check viewing permission for teams
		if ( !$isjury && !canViewClarification($team, $clar))
			continue;

		$clar['clarid'] = (int)$clar['clarid'];
		
		if(isset($clar['unread']))
			echo '<tr class="unread">';
		else
			echo '<tr>';
		
	echo '<td>' . make_link($clar['clarid'], "clarification.php?id=" . urlencode($clar['clarid'])) . '</td>';

		$sender = $clar['sender'];
		$recipient = $clar['recipient'];
		
		if ($sender == NULL && $recipient == NULL) {
			$sender = 'Jury';
			$recipient = 'All';
		} else {
			if ($sender == NULL)
				$sender = 'Jury';
			else
				$sender = make_link($sender, "team.php?id=" . urlencode($clar['sender']), $isjury);

			if ($recipient == NULL)
				$recipient = 'Jury';
			else
				$recipient = make_link($recipient, "team.php?id=" . urlencode($clar['recipient']), $isjury);
		}


		echo '<td class="teamid">' . $sender . '</td>';
		echo '<td class="teamid">' . $recipient . '</td>';

		echo '<td>' . printtime($clar['submittime']) . '</td>';
		echo '<td><a href="clarification.php?id=' . urlencode($clar['clarid']) . '">';
		echo summarizeClarification($clar['body']);
		echo "</a></td></tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

/**
 * Output a form to send a new clarification.
 * Set team to a login, to make only that team (or ALL) selectable.
 */
function putClarificationForm($action, $isjury = FALSE, $respid = NULL)
{
	require_once('forms.php');

	global $DB;
?>

<script type="text/javascript">
<!--
function confirmClar() {
<?php if ( $isjury ): ?>
	var sendto = document.forms['sendclar'].sendto.value;
	if ( sendto=='domjudge-must-select' ) {
		alert('You must select a recipient for this clarification.');
		return false;
	}
	if ( sendto=='' ) sendto = "ALL";
	return confirm("Send clarification to " + sendto + "?");
<?php else : ?>
	return confirm("Send clarification request to Jury?");
<?php endif; ?>
}
// -->
</script>
	  
<?php
	echo addForm($action, 'post', 'sendclar');
	echo "<table>\n";

	if ( $respid ) {
		$clar = $DB->q('MAYBETUPLE SELECT c.*, t.name AS toname, f.name AS fromname
		                FROM clarification c
		                LEFT JOIN team t ON (t.login = c.recipient)
		                LEFT JOIN team f ON (f.login = c.sender)
		                WHERE c.clarid = %i', $respid);
	}

	if ( $isjury ) { // list all possible recipients in the "sendto" box
		echo "<tr><td><b><label for=\"sendto\">Send to</label>:</b></td><td>\n";

		if ( !empty($respid) ) {
			echo addHidden('id',$respid);
		}

		$options = array('domjudge-must-select' => '(select...)', '' => 'ALL');
		if ( ! $respid ) {
			$teams = $DB->q('KEYVALUETABLE SELECT login, CONCAT(login, ": ", name) as name
			                 FROM team
			                 ORDER BY categoryid ASC, name ASC');
			$options = array_merge($options,$teams);
		} else {
			if ( $clar['sender'] ) {
				$options[$clar['sender']] = $clar['sender'] .': '.
					$clar['fromname'];
			} else if ( $clar['recipient'] ) {
				$options[$clar['recipient']] = $clar['recipient'] .': '.
					$clar['toname'];
			}
		}
		echo addSelect('sendto', $options, 'domjudge-must-select', true);
		echo "</td></tr>\n";
	} else {
		echo "<tr><td><b>To:</b></td><td>Jury</td></tr>\n";
	}

	?>
<tr>
<td valign="top"><b><label for="bodytext">Text</label>:</b></td>
<td><?php
$body = "";
if ( $respid ) {
	$text = explode("\n",wordwrap($clar['body']),70);
	foreach($text as $line) {
		$body .= "> $line\n";
	}
	$body .= "\n";
}
echo addTextArea('bodytext', $body, 80, 10);
?></td></tr>
<tr>
<td>&nbsp;</td>
<td><?php echo addSubmit('Send', 'submit', 'return confirmClar()'); ?></td>
</tr>
</table>
</form>
<script type="text/javascript">
<!--
document.forms['sendclar'].bodytext.focus();
// -->
</script>
<?php

}
