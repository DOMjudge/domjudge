<?php

/**
 * Common functions in jury interface
 *
 * $Id$
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
		if ( $validresult && $validresult != 'correct' ) {
			$ret .= " onclick=\"return confirm('Rejudge submission s" .
				(int)$id . "?')\" />\n";
		} else {
			$ret .= " disabled=\"disabled\" />\n";
		}
	} else {
		$ret .= '<input type="submit" value="REJUDGE ALL for ' .
			$table . ' ' . htmlspecialchars($id) .
			'" onclick="return confirm(\'Rejudge all submissions for this ' .
			$table . "?')\" />\n";
	}
	return $ret . addEndForm();
}

/**
 * Try to include the PEAR Text/Highlighter class.
 * Returns bool indicating success.
 */
function include_highlighter()
{
	// Disable warning output so include can fail, but display fatal errors,
	// since these will halt processing of the entire script.
	$old_e_r = error_reporting();
	error_reporting($old_e_r & ~ E_WARNING);

	include('Text/Highlighter.php');

	// Restore error reporting to the old level
	error_reporting($old_e_r);

	return class_exists('Text_Highlighter');
}
