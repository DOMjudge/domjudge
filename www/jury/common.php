<?php

/**
 * Common functions in jury interface
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

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

/**
 * Returns a form to rejudge all judgings based on a (table,id)
 * pair. For example, to rejudge all for language 'java', call
 * as rejudgeForm('language', 'java').
 */
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

		// disable the form button if there are no valid judgings anyway
		// (nothing to rejudge) or if the result is already correct
		global $DB;
		$validresult = $DB->q('MAYBEVALUE SELECT result FROM judging WHERE
		                       submitid = %i AND valid = 1', $id);
		if ( IS_ADMIN ) {
			if ( ! $validresult ) {
				$ret .= " onclick=\"return confirm('Rejudge PENDING submission s" .
					(int)$id . ", are you sure?')\" />\n";
			} elseif ( $validresult == 'correct' ) {
				$ret .= " onclick=\"return confirm('Rejudge CORRECT submission s" .
					(int)$id . ", are you sure?')\" />\n";
			} else {
				$ret .= " onclick=\"return confirm('Rejudge submission s" .
					(int)$id . "?')\" />\n";
			}
		} else {
			if ( $validresult && $validresult != 'correct' ) {
				$ret .= " onclick=\"return confirm('Rejudge submission s" .
					(int)$id . "?')\" />\n";
			} else {
				$ret .= " disabled=\"disabled\" />\n";
			}
		}
	} else {
		$ret .= '<input type="submit" value="REJUDGE ALL for ' .
			$table . ' ' . htmlspecialchars($id) .
			'" onclick="return confirm(\'Rejudge all submissions for this ' .
			$table . "?')\" />\n";
	}
	return $ret . addEndForm();
}
