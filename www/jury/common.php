<?php

/**
 * Common functions in jury interface
 *
 * $Id$
 */


/**
 * Outputs a list of judgings, limited by key=value
 *
 * $key can only be one of (submitid,judgehost)
 */
function putJudgings($key, $value) {
	global $DB;
	
	if ( empty($key) || empty($value) ) {
		error("no key or value passed for selection in judging output");
	}

	// get the judgings for a specific key and value pair
	// select only specific fields to avoid retrieving large blobs
	$res = $DB->q('SELECT judgingid, submitid, starttime, endtime, judgehost,
	               result, verified, valid FROM judging
	               WHERE cid = %i AND ' .
				   ( $key == 'submitid' ? 'submitid = %s' : '' ) .
				   ( $key == 'judgehost' ? 'judgehost = %s' : '' ) .
				   ' ORDER BY starttime DESC',
	              getCurContest(), $value);

	if( $res->count() == 0 ) {
		echo "<p><em>No judgings.</em></p>\n\n";
	} else {
		echo "<table class=\"list\">\n<tr><th>ID</th><th>start</th><th>end</th>";
		if ( $key != 'judge' ) echo "<th>judge</th>";
		echo "<th>result</th><th>valid</th><th>verified</th>";
		echo "</tr>\n";
		while( $jud = $res->next() ) {
			echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] .
				'">j' .	(int)$jud['judgingid'] . '</a></td>';
			echo '<td>' . printtime($jud['starttime']) . '</td>';
			echo '<td>' . printtime(@$jud['endtime'])  . '</td>';
			echo '<td><a href="judgehost.php?id=' . urlencode(@$jud['judgehost']) .
				'">' . printhost(@$jud['judgehost']) . '</a></td>';
			echo '<td><a href="judging.php?id=' . (int)$jud['judgingid'] . '">' .
				printresult(@$jud['result'], $jud['valid']) . '</a></td>';
			echo '<td align="center">' . printyn($jud['valid']) . '</td>';
			echo '<td align="center">' . printyn($jud['verified']) . '</td>';
			echo "</tr>\n";
		}
		echo "</table>\n\n";
	}

	return;
}

/**
 * Return a link to add a new row to a specific table.
 */
function addLink($table, $multi = false)
{
	return "<a href=\"" . htmlspecialchars($table) . ".php?cmd=add\">" .
		"<img src=\"../images/add" . ($multi?"-multi":"") .
		".png\" alt=\"add" . ($multi?" multiple":"") .
		"\" title=\"add" .   ($multi?" multiple":"") .
		" new " . htmlspecialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to edit a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 */
function editLink($table, $value, $multi = false)
{
	return "<a href=\"" . htmlspecialchars($table) . ".php?cmd=edit" .
		($multi ? "" : "&amp;id=" . urlencode($value) ) . "\">" .
		"<img src=\"../images/edit" . ($multi?"-multi":"") .
		".png\" alt=\"edit" . ($multi?" multiple":"") .
		"\" title=\"edit " .   ($multi?"multiple ":"this ") .
		htmlspecialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to delete a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 */
function delLink($table, $field, $value)
{
	return "<a href=\"delete.php?table=" . urlencode($table) . "&amp;" .
		$field . "=" . urlencode($value) ."\"><img src=\"../images/delete.png\"" .
		"alt=\"delete\" title=\"delete this " . htmlspecialchars($table) . 
		"\" class=\"picto\" /></a>";
}

function rejudgeForm($table, $id)
{
	require_once('../forms.php');

	$ret = addForm('rejudge.php') .
		addHidden('table', $table) .
		addHidden('id', $id);

	// special case submission
	if ( $table == 'submission' ) {
		$ret .= "<input type=\"submit\" value=\"REJUDGE submission s" .
			(int)$id . "\"";

		global $DB;
		$iscorrect = (bool)$DB->q('VALUE SELECT count(judgingid) FROM judging WHERE
                           submitid = %i AND valid = 1 AND result = "correct"', $id);

		if ( !$iscorrect ) {
			$ret .= " onclick=\"return confirm('Rejudge submission s" .
				(int)$id . "?')\" />\n";
		} else {
			$ret .= " disabled=\"disabled\" />\n";
		}
	} else {
		$ret .= "<input type=\"submit\" value=\"REJUDGE ALL for " .
			$table . " " . htmlspecialchars($id) .
		"\" onclick=\"return confirm('Rejudge all submissions for this " .
			$table . "?')\" />\n";
	}
	return $ret . addEndForm();
}

