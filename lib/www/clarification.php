<?php
/**
 * Clarification helper functions for jury and teams
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
 * Returns the list of clarification categories as a key,value array.
 * Keys are prepended with '#' to distinguish them from problem IDs.
 */
function getClarCategories(&$default = null)
{
	$categs = dbconfig_get('clar_categories');

	$clarcategories = array();
	foreach ( $categs as $key => $val ) $clarcategories['#'.$key] = $val;

	return $clarcategories;
}

/**
 * Output a single clarification.
 * Helperfunction for putClarification, do _not_ use directly!
 */
function putClar($clar)
{
	if ( $clar['sender'] ) {
		$from = '<span class="teamid">' . htmlspecialchars($clar['sender']) .
			'</span>: ' . htmlspecialchars($clar['fromname']);
	} else {
		$from = 'Jury';
		if ( IS_JURY ) $from .= ' (' . htmlspecialchars($clar['jury_member']) . ')';
	}
	if ( $clar['recipient'] && $from == 'Jury' ) {
		$to = '<span class="teamid">' . htmlspecialchars($clar['recipient']) .
			'</span>: ' . htmlspecialchars($clar['toname']);
	} else {
		$to = ( $clar['sender'] ) ? 'Jury' : 'All';
	}

	echo "<table>\n";

	echo '<tr><td scope="row">From:</td><td>';
	if ( IS_JURY && $clar['sender']) {
		echo '<a href="team.php?id=' . urlencode($clar['sender']) . '">' .
			$from . '</a>';
	} else {
		echo $from;
	}
	echo "</td></tr>\n";

	echo '<tr><td scope="row">To:</td><td>';
	if ( IS_JURY && $clar['recipient']) {
		echo '<a href="team.php?id=' . urlencode($clar['recipient']) . '">' .
			$to . '</a>';
	} else {
		echo $to;
	}
	echo "</td></tr>\n";

	$categs = getClarCategories();

	echo '<tr><td scope="row">Subject:</td><td>';
	if ( empty($clar['probid']) ) { /* empty */ }
	elseif ( substr($clar['probid'], 0, 1)=='#' ) {
		echo $categs[$clar['probid']];
	} else {
		if ( IS_JURY ) {
			echo '<a href="problem.php?id=' . urlencode($clar['probid']) . '">' .
				'Problem ' . $clar['probid'].": ".$clar['probname'] . '</a>';
		} else {
			echo 'Problem ' . $clar['probid'].": ".$clar['probname'];
		}
	}
	echo "</td></tr>\n";

	echo '<tr><td scope="row">Time:</td><td>';
	echo printtime($clar['submittime'], TRUE);
	echo "</td></tr>\n";

	echo '<tr><td></td><td class="filename">';
	echo '<pre class="output_text">' . htmlspecialchars(wrap_unquoted($clar['body'],80)) . "</pre>";
	echo "</td></tr>\n";

	echo "</table>\n";

	return;
}

/**
 * Output a clarification (and thread) for id $id.
 */
function putClarification($id,  $team = NULL)
{
	if ( $team==NULL && ! IS_JURY ) {
		error("access denied to clarifications: you seem to be team nor jury");
	}

	global $DB;

	$clar = $DB->q('TUPLE SELECT * FROM clarification WHERE clarid = %i', $id);

	$clars = $DB->q('SELECT c.*, p.name AS probname, t.name AS toname, f.name AS fromname
	                 FROM clarification c
	                 LEFT JOIN problem p ON (c.probid = p.probid AND p.allow_submit = 1)
	                 LEFT JOIN team t ON (t.login = c.recipient)
	                 LEFT JOIN team f ON (f.login = c.sender)
	                 WHERE c.respid = %i OR c.clarid = %i
	                 ORDER BY c.submittime, c.clarid',
	                $clar['clarid'], $clar['clarid']);

	while ( $clar = $clars->next() ) {
		// check permission to view this clarification
		if (IS_JURY || canViewClarification($team, $clar)) {
			setClarificationViewed($clar['clarid'], $team);
			putClar($clar);
			echo "<br />\n\n";
		}
	}
}

/**
 * Summarize a clarification.
 * Helper function for putClarificationList.
 */
function summarizeClarification($body, $maxchars = 80)
{
	// when making a summary, try to ignore the quoted text, and
	// replace newlines by spaces.
	$split = explode("\n", $body);
	$newbody = '';
	foreach($split as $line) {
		if ( strlen($line) > 0 && $line{0} != '>' ) $newbody .= $line . ' ';
	}
	return htmlspecialchars( str_cut( ( empty($newbody) ? $body : $newbody ), $maxchars) );
}

/**
 * Print a list of clarifications in a table with links to the clarifications.
 */
function putClarificationList($clars, $team = NULL)
{
	if ( $team==NULL && ! IS_JURY ) {
		error("access denied to clarifications: you seem to be team nor jury");
	}

	echo "<table class=\"list sortable\">\n<thead>\n<tr>" .
		( IS_JURY ? "<th scope=\"col\">ID</th>" : "") .
	     "<th scope=\"col\">time</th>" .
	     "<th scope=\"col\">from</th>" .
	     "<th scope=\"col\">to</th><th scope=\"col\">subject</th>" .
	     "<th scope=\"col\">text</th>" .
		( IS_JURY ? "<th scope=\"col\">answered</th><th scope=\"col\">by</th>" : "") .
	     "</tr>\n</thead>\n<tbody>\n";

	$categs = getClarCategories();

	while ( $clar = $clars->next() ) {
		// check viewing permission for teams
		if ( ! IS_JURY && !canViewClarification($team, $clar) ) continue;

		$clar['clarid'] = (int)$clar['clarid'];
		$link = '<a href="clarification.php?id=' . urlencode($clar['clarid'])  . '">';

		if ( isset($clar['unread']) ) {
			echo '<tr class="unread">';
		} else {
			echo '<tr>';
		}

		if ( IS_JURY ) {
			echo '<td>' . $link . $clar['clarid'] . '</a></td>';
		}

		echo '<td>' . $link . printtime($clar['submittime'], TRUE) . '</a></td>';

		$sender = htmlspecialchars($clar['sender']);
		$recipient = htmlspecialchars($clar['recipient']);

		if ($sender == NULL && $recipient == NULL) {
			$sender = 'Jury';
			$recipient = 'All';
		} else {
			if ( $sender    == NULL ) $sender = 'Jury';
			if ( $recipient == NULL ) $recipient = 'Jury';
		}

		echo '<td class="teamid">' . $link . $sender . '</a></td>' .
		     '<td class="teamid">' . $link . $recipient . '</a></td>';

		echo '<td>' . $link;
		if ( empty($clar['probid']) ) { /* empty */ }
		elseif ( substr($clar['probid'], 0, 1)=='#' ) {
			echo $categs[$clar['probid']];
		} else {
			echo "problem ".$clar['probid'];
		}
		echo "</a></td>";

		echo '<td class="clartext">' . $link .
		    summarizeClarification($clar['body']) . "</a></td>";

		if ( IS_JURY ) {
			unset($answered, $jury_member);
			$claim = FALSE;
			$answered = printyn($clar['answered']);
			if ( empty($clar['jury_member']) ) {
				$jury_member = '&nbsp;';
			} else {
				$jury_member = htmlspecialchars($clar['jury_member']);
			}
			if ( !$clar['answered'] ) {
				if ( empty($clar['jury_member']) ) {
					$claim = TRUE;
				} else {
					$answered = 'claimed';
				}
			}

			echo "<td>$link $answered</a></td><td>";
			if ( $claim && isset($clar['sender']) ) {
				echo "<a class=\"button\" href=\"clarification.php?claim=1&amp;id=" .
					htmlspecialchars($clar['clarid']) . "\">claim</a>";
			} else {
				if ( !$clar['answered'] && $jury_member==getJuryMember() ) {
					echo "<a class=\"button\" href=\"clarification.php?unclaim=1&amp;id=" .
						htmlspecialchars($clar['clarid']) . "\">unclaim</a>";
				} else {
					echo "$link $jury_member</a>";
				}
			}
			echo "</td>";
		}
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}

/**
 * Output a form to send a new clarification.
 * Set team to a login, to make only that team (or ALL) selectable.
 */
function putClarificationForm($action, $cid, $respid = NULL)
{
	if ( empty($cid) ) {
		echo '<p class="nodata">No active contest</p>';
		return;
	}

	require_once('forms.php');

	global $DB, $cdata;
?>

<script type="text/javascript">
<!--
function confirmClar() {
<?php if ( IS_JURY ): ?>
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
<?php if ( IS_JURY ): ?>
function replaceAnswer() {
	var newtext = document.forms['sendclar'].answertext.value;
	var elem = document.getElementById('bodytext');
	elem.innerHTML = newtext;
	return false;
}
<?php endif; ?>
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

	if ( IS_JURY ) { // list all possible recipients in the "sendto" box
		echo "<tr><td><b><label for=\"sendto\">Send to</label>:</b></td><td>\n";

		if ( !empty($respid) ) {
			echo addHidden('id',$respid);
		}

		$options = array('domjudge-must-select' => '(select...)', '' => 'ALL');
		if ( ! $respid ) {
			$teams = $DB->q('KEYVALUETABLE SELECT login, CONCAT(login, ": ", name) as name
			                 FROM team
			                 ORDER BY categoryid ASC, team.name COLLATE utf8_general_ci ASC');
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

	// Select box for a specific problem (only when the contest
	// has started) or other issue.
	if ( difftime($cdata['starttime'], now()) <= 0 ) {
		$probs = $DB->q('KEYVALUETABLE SELECT probid, CONCAT(probid, ": ", name) as name
		                 FROM problem WHERE cid = %i AND allow_submit = 1
		                 ORDER BY probid ASC', $cid);
	}
	$categs = getClarCategories();
	$defclar = key($categs);
	$options = array_merge($probs, $categs);
	echo "<tr><td><b>Subject:</b></td><td>\n" .
	     addSelect('problem', $options, ($respid ? $clar['probid'] : $defclar), true) .
	     "</td></tr>\n";

	?>
<tr>
<td><b><label for="bodytext">Text</label>:</b></td>
<td><?php
$body = "";
if ( $respid ) {
	$text = explode("\n",wrap_unquoted($clar['body']),75);
	foreach($text as $line) $body .= "> $line\n";
}
echo addTextArea('bodytext', $body, 80, 10) . "</td></tr>\n";

$std_answers = dbconfig_get('clar_answers');
if ( IS_JURY && $respid!==NULL && count($std_answers)>0 ) {
	$options = array();
	$default = $std_answers[0];
	foreach($std_answers as $ans) $options[$ans] = summarizeClarification($ans, 50);
	echo "<tr><td><b>Std. answer:</b></td><td>" .
	    addSelect('answertext', $options, $default, TRUE) .
	    addSubmit('replace', 'replace', 'return replaceAnswer()') . "</td></tr>\n";
}

?>
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
