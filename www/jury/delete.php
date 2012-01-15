<?php
/**
 * Functionality to delete data from this interface.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

require(LIBDIR . '/relations.php');

$t = @$_REQUEST['table'];
$referrer = @$_REQUEST['referrer'];
if ( ! preg_match('/^[._a-zA-Z0-9?&=]*$/', $referrer ) ) error ("Invalid characters in referrer.");

if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$k = array();
foreach($KEYS[$t] as $key) {
	$k[$key] = @$_REQUEST[$key];
	if ( !$k[$key] ) error ("I can't find my keys.");
}

if ( isset($_POST['cancel']) ) {

	if ( !empty($referrer) ) {
		header('Location: ' . $referrer);
	} else {
		header('Location: '.$t.'.php?id=' .
			urlencode(array_shift($k)));
	}
	exit;
}

// Send headers here, because we need to be able to redirect above this point.

$title = 'Delete from ' . $t;
require(LIBWWWDIR . '/header.php');

// Check if we can really delete this.
$warnings = array();
foreach($k as $key => $val) {
	if ( ($tables = fk_check ("$t.$key", $val))!==NULL ) {
		foreach ( $tables as $table => $action ) {
			switch ( $action ) {
			case 'RESTRICT':
				error("$t.$key \"$val\" is still referenced in $table, cannot delete.");
			case 'CASCADE':
				$warnings[] = "cascade to $table";
				break;
			case 'SETNULL':
				$warnings[] = "create dangling references in $table";
				break;
			case 'NOCONSTRAINT':
				break;
			default:
				error("$t.$key is referenced in $table with unknown action '$action'.");
			}
		}
	}
}

if (isset($_POST['confirm'] ) ) {

	// LIMIT 1 is a security measure to prevent our bugs from
	// wiping a table by accident.
	$DB->q("DELETE FROM $t WHERE %S LIMIT 1", $k);
	auditlog($t, implode(', ', $k), 'deleted');

	echo "<p>" . ucfirst($t) . " <strong>" . htmlspecialchars(implode(", ", $k)) .
		"</strong> has been deleted.</p>\n\n";

	if ( !empty($referrer) ) {
		echo "<p><a href=\"" . $referrer .  "\">back to overview</a></p>";
	} else {
		// one table falls outside the predictable filenames
		$tablemulti = ($t == 'team_category' ? 'team_categories' : $t.'s');
		echo "<p><a href=\"" . $tablemulti . ".php\">back to $tablemulti</a></p>";
	}
} else {
	echo addForm('delete.php') .
		addHidden('table', $t);
	foreach ( $k as $key => $val ) {
		echo addHidden($key, $val);
	}

	echo msgbox (
		"Really delete?",
		"You're about to delete $t <strong>" .
		htmlspecialchars(join(", ", array_values($k))) . "</strong>.<br />\n" .
		(count($warnings)>0 ? "<br /><strong>Warning, this will:</strong><br />" .
		 implode('<br />', $warnings) : '' ) . "<br /><br />\n" .
		"Are you sure?<br /><br />\n\n" .
		( empty($referrer) ? '' : addHidden('referrer', $referrer) ) .
		addSubmit(" Never mind... ", 'cancel') .
		addSubmit(" Yes I'm sure! ", 'confirm') );

	echo addEndForm();
}


require(LIBWWWDIR . '/footer.php');
