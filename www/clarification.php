<?php
/**
 * Clarification helper functions for jury and teams
 *
 * $Id$
 */

/**
 * Output a single clarification.
 * Helperfunction for putClarification, do _not_ use directly!
 */
function putClar($clar, $isjury = FALSE) 
{
	if ( $clar['sender'] ) {
		$from = $clar['sender'] . ': ' . $clar['fromname'];
	} else {
		$from = 'Jury';
	}
	if ( $clar['recipient'] && $from == 'Jury' ) {
		$to = $clar['recipient'] . ': ' . $clar['toname'];
	} else {
		$to = ( $from == 'Jury' ) ? 'All' : 'Jury' ;
	}
	$fromlink = $isjury && $clar['sender'];
	$tolink   = $isjury && $clar['recipient'];

	echo "<table>\n";
	echo '<tr><td>From:</td>' . 
		'<td><span class="teamid">' .
		( $fromlink ? '<a href="team.php?id=' . urlencode($clar['sender']) .
			'">' : '' ) . '</span>' .
		htmlspecialchars($from) . ( $fromlink ? '</a>' : '') . "</td></tr>\n";
	echo  '<tr><td>To:</td>' .
		'<td>' .
		( $tolink ? '<a href="team.php?id=' . urlencode($clar['recipient']) .
			'">' : '' ) .
		htmlspecialchars($to) . ( $tolink ? '</a>' : '') . "</td></tr>\n";
	echo '<tr><td>Time:</td><td>' . printtime($clar['submittime']) .
		"</td></tr>\n";
	echo '<tr><td valign="top"></td><td class="filename">' .
		'<pre class="output_text">' .
		wordwrap(htmlspecialchars($clar['body'])) . "</pre></td></tr>\n";
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

	echo "<table>\n";
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
		echo '<tr>';
		echo '<td><a href="' .
			addUrl('clarification.php?id=' . $clar['clarid'], $popupTag) .
			'">' . $clar['clarid'] . '</a></td>';
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
		echo '<td><a href="' .
			addUrl('clarification.php?id=' . $clar['clarid'], $popupTag) . '">' .
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

	// insert the timestamp in links for teams to handle popups
	if ( ! $isjury ) {
		global $popupTag;
	} else {
		$popupTag = NULL;
	}

	echo '<form action="' . addUrl(urlencode($action), $popupTag) .
		"\" method=\"post\">\n";


	echo "<table>\n";

	if ( $isjury ) { // list all possible recipients in the "sendto" box
		echo "<tr><td><b>Send to:</b></td><td>\n";

		if ( !empty($respid) ) {
			echo '<input type="hidden" name="id" value="' . $respid . "\" />\n";
		}

		echo "<select name=\"sendto\">\n";
		echo "<option value=\"\">ALL</option>\n";

		if ( ! $respid ) {
			$teams = $DB->q('SELECT login, name FROM team
				ORDER BY category ASC, name ASC');
			while ( $team = $teams->next() ) {
				echo '<option value="' .
					htmlspecialchars($team['login']) . '">' .
					htmlspecialchars($team['login']) . ': ' .
					htmlentities($team['name']) . "</option>\n";
			}
		} else {
			$clar = $DB->q('MAYBETUPLE SELECT c.*,
				t.name AS toname, f.name AS fromname
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
<td valign="top"><b>Text:</b></td>
<td><textarea name="bodytext" cols="80" rows="10"></textarea></td>
</tr>
<tr>
<td>&nbsp;</td>
<td><input type="submit" name="submit" value="Send" /></td>
</tr>
</table>
</form>
<?php

}
