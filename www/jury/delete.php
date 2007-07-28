<?php
/**
 * Functionality to delete data from this interface.
 *
 * $Id$
 */
require('init.php');
requireAdmin();

require(SYSTEM_ROOT . '/lib/relations.php');

$t = @$_REQUEST['table'];

if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$k = array();
foreach($KEYS[$t] as $key) {
	$k[$key] = @$_REQUEST[$key];
	if ( !$k[$key] ) error ("I can't find my keys.");
}

if ( isset($_POST['cancel']) ) {

	// this probably is not generic enough for the future, but
	// works for all our current tables.
	header('Location: '.getBaseURI().'jury/'.$t.'.php?id=' .
		urlencode(array_shift($k)));
	exit;
}

// Send headers here, because we need to be able to redirect above this point.

$title = 'Delete from ' . $t;
require('../header.php');

// Check if we can really delete this.
foreach($k as $key => $val) {
	if ( $errtable = fk_check ( "$t.$key", $val ) ) {
		error ( "$t.$key \"$val\" is still referenced in $errtable, cannot delete." );
	}
}

if (isset($_POST['confirm'] ) ) {

	// LIMIT 1 is a security measure to prevent our bugs from
	// wiping a table by accident.
	$DB->q("DELETE FROM $t WHERE %S LIMIT 1", $k);

	echo "<p>" . ucfirst($t) . " <strong>" . htmlspecialchars(implode(", ", $k)) . 
		"</strong> has been deleted.</p>\n\n";
	// one table falls outside the predictable filenames
	$tablemulti = ($t == 'team_category' ? 'team_categories' : $t.'s');
	echo "<p><a href=\"" . $tablemulti . ".php\">back to $tablemulti</a></p>";

} else {
	require_once('../forms.php');

	echo addForm('delete.php') .
		addHidden('table', $t);
	foreach ( $k as $key => $val ) {
		echo addHidden($key, $val);
	}

	echo msgbox ( 
		"Really delete?",
		"You're about to delete $t <strong>" .
		htmlspecialchars(join(", ", array_values($k))) . "</strong>.<br /><br />\n\n" .
		"Are you sure?<br /><br />\n\n" .
		addSubmit(" Never mind... ", 'cancel') .
		addSubmit(" Yes I'm sure! ", 'confirm') );

	echo addEndForm();
}


require('../footer.php');
