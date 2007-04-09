<?php
/**
 * Clarification helper functions for jury and teams
 *
 * $Id$
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

	// insert the timestamp in links for teams to handle popups
	if ( ! $isjury ) {
		global $popupTag;
	} else {
		$popupTag = NULL;
	}

	echo "<table class=\"list\">\n";
	echo "<tr><th>ID</th><th>from</th><th>to</th>" .
		"<th>time</th><th>text</th></tr>\n";

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
		echo '<td><a href="clarification.php?id='.$clar['clarid'].'">' .
			htmlspecialchars(str_cut($clar['body'],80)) . "</a></td></tr>\n";
	}
	echo "</table>\n\n";
}

/**
 * Output a form to send a new clarification.
 * Set team to a login, to make only that team (or ALL) selectable.
 */
function putClarificationForm($action, $isjury = FALSE, $respid = NULL)
{
	global $DB;
?>

<script type="text/javascript">
<!--
function confirmClar() {
<?php if ( $isjury ): ?>
	sendto = document.forms['sendclar'].sendto.value;
	if ( sendto=='' ) sendto = "ALL";
	return confirm("Send clarification to " + sendto + "?");
<?php else : ?>
	return confirm("Send clarification to Jury?");
<?php endif; ?>
}
// -->
</script>
	  
<?php
	  
	echo '<form id="sendclar" action="'.$action."\" method=\"post\">\n";

	echo "<table>\n";

	if ( $isjury ) { // list all possible recipients in the "sendto" box
		echo "<tr><td><b><label for=\"sendto\">Send to</label>:</b></td><td>\n";

		if ( !empty($respid) ) {
			echo '<input type="hidden" name="id" value="' . htmlspecialchars($respid)
				. "\" />\n";
		}

		echo "<select name=\"sendto\" id=\"sendto\">\n";
		echo "<option value=\"\">ALL</option>\n";

		if ( ! $respid ) {
			$teams = $DB->q('SELECT login, name FROM team
			                 ORDER BY categoryid ASC, name ASC');
			while ( $team = $teams->next() ) {
				echo '<option value="' .
					htmlspecialchars($team['login']) . '">' .
					htmlspecialchars($team['login']) . ': ' .
					htmlentities($team['name']) . "</option>\n";
			}
		} else {
			$clar = $DB->q('MAYBETUPLE SELECT c.*, t.name AS toname, f.name AS fromname
			                FROM clarification c
			                LEFT JOIN team t ON (t.login = c.recipient)
			                LEFT JOIN team f ON (f.login = c.sender)
			                WHERE c.clarid = %i', $respid);
			if ( $clar['sender'] ) {
				echo '<option selected="selected" value="' .
					htmlspecialchars($clar['sender']) . '">' .
					htmlspecialchars($clar['sender']) . ': ' .
					htmlentities($clar['fromname']) . "</option>\n";
			} else if ( $clar['recipient'] ) {
				echo '<option selected="selected" value="' .
					htmlspecialchars($clar['recipient']) . '">' .
					htmlspecialchars($clar['recipient']) . ': ' .
					htmlentities($clar['toname']) . "</option>\n";
			}
		}
		echo "</select>\n";
		echo "</td></tr>\n";
	} else {
		echo "<tr><td><b>To:</b></td><td>Jury</td></tr>\n";
	}

	?>
<tr>
<td valign="top"><b><label for="bodytext">Text</label>:</b></td>
<td><textarea name="bodytext" cols="80" rows="10" id="bodytext"><?php
if ( $respid ) {
	$text = explode("\n",wordwrap(htmlspecialchars($clar['body']),70));
	foreach($text as $line) {
		echo "&gt; $line\n";
	}
	echo "\n";
}
?></textarea></td>
</tr>
<tr>
<td>&nbsp;</td>
<td><input type="submit" name="submit" value="Send" onclick="return confirmClar()" /></td>
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
