<?php
/**
 * Clarification helper functions for jury and teams
 *
 * $Id$
 */

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
	$fromlink = $isjury && $clar['sender'];
	$tolink   = $isjury && $clar['recipient'];

	echo "<table>\n";
	echo '<tr><td>From:</td>' . 
		'<td>' .
		( $fromlink ? '<a href="team.php?id=' . urlencode($clar['sender']) .
			'">' : '' ) .
		$from . ( $fromlink ? '</a>' : '') . "</td></tr>\n";
	echo  '<tr><td>To:</td>' .
		'<td>' .
		( $tolink ? '<a href="team.php?id=' . urlencode($clar['recipient']) .
			'">' : '' ) .
		$to . ( $tolink ? '</a>' : '') . "</td></tr>\n";
	echo '<tr><td>Time:</td><td>' . printtime($clar['submittime']) .
		"</td></tr>\n";
	echo '<tr><td valign="top"></td><td class="filename">' .
		'<pre class="output_text">' .
		wordwrap(htmlspecialchars($clar['body']),80) . "</pre></td></tr>\n";
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
		ORDER BY c.submittime', $clar['clarid'], $clar['clarid']);

	while ( $clar = $clarifications->next() ) {
		// check permission to view this clarification
		if ( $isjury || ( $clar['sender']==$team || ( $clar['sender']==NULL &&
			( $clar['recipient']==NULL || $clar['recipient']==$team ) ) ) ) {
			setClarificationViewed($clar['clarid'], $team);
			putClar($clar,$isjury);
			echo "<p></p>\n\n";
		}
	}
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
	echo "<tr><th>ID</th><th>from</th><th>to</th>" .
		"<th>time</th><th>text</th></tr>\n</thead>\n<tbody>\n";

	while ( $clar = $clars->next() ) {
		// check viewing permission for teams
		if ( ! $isjury ) {
			if ( ! ( ($clar['sender']==NULL &&
				( $clar['recipient']==NULL || $clar['recipient']==$team ) ) ||
				( $clar['sender']==$team ) ) ) continue;
		}
		$clar['clarid'] = (int)$clar['clarid'];
		
		if(isset($clar['unread']))
			echo '<tr class="unread">';
		else
			echo '<tr>';
		
		echo '<td><a href="clarification.php?id='.$clar['clarid'].'">'
			. $clar['clarid'] . '</a></td>';
		if ( $isjury ) {
			echo '<td class="teamid">' . ( $clar['sender'] ?
				'<a href="team.php?id=' . urlencode($clar['sender']) . '">' .
				htmlspecialchars($clar['sender']) . '</a>' :
				'Jury' ) . '</td>';
			echo '<td class="teamid">' . ( $clar['recipient'] ?
				'<a href="team.php?id=' . urlencode($clar['recipient']) . '">' .
				htmlspecialchars($clar['recipient']) . '</a>' :
				( $clar['sender'] ? 'Jury' : 'All') ) . '</td>';
		} else {
			echo '<td class="teamid">' . ( $clar['sender'] ?
				htmlspecialchars($clar['sender']) : 'Jury' ) . '</td>';
			echo '<td class="teamid">' . ( $clar['recipient'] ?
				htmlspecialchars($clar['recipient']) :
				( $clar['sender'] ? 'Jury' : 'All') ) . '</td>';
		}
		echo '<td>' . printtime($clar['submittime']) . '</td>';
		echo '<td><a href="clarification.php?id='.$clar['clarid'].'">';

		// when making a summary, try to igonore the quoted text
		$split = explode("\n", $clar['body']);
		$newbody = '';
		foreach($split as $line) {
			if ( strlen($line) > 0 && $line{0} != '>' ) $newbody .= $line;
		}
		echo htmlspecialchars( str_cut( ( empty($newbody) ? $clar['body'] : $newbody ), 80) );
		
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
	sendto = document.forms['sendclar'].sendto.value;
	if ( sendto=='domjudge-must-select' ) {
		alert('You must select a recipient for this clarification.');
		return false;
	}
	if ( sendto=='' ) sendto = "ALL";
	return confirm("Send clarification to " + sendto + "?");
<?php else : ?>
	return confirm("Send clarification to Jury?");
<?php endif; ?>
}
// -->
</script>
	  
<?php
	echo addForm($action, 'post', 'sendclar');
	echo "<table>\n";

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
			$clar = $DB->q('MAYBETUPLE SELECT c.*, t.name AS toname, f.name AS fromname
			                FROM clarification c
			                LEFT JOIN team t ON (t.login = c.recipient)
			                LEFT JOIN team f ON (f.login = c.sender)
			                WHERE c.clarid = %i', $respid);
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
