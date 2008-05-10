<?php
/**
 * Functionality to edit data from this interface.
 *
 * TODO:
 *  - Does not support checkboxes yet, since these
 *    return no value when not checked.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

$cmd = @$_POST['cmd'];
if ( $cmd != 'add' && $cmd != 'edit' ) error ("Unknown action.");

require(SYSTEM_ROOT . '/lib/relations.php');

$t = @$_POST['table'];
if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$data          =  $_POST['data'];
$keydata       = @$_POST['keydata'];
$skipwhenempty = @$_POST['skipwhenempty'];
$referrer      = @$_POST['referrer'];

if ( empty($data) ) error ("No data.");

require(SYSTEM_ROOT . '/lib/www/checkers.jury.php');

foreach ($data as $i => $itemdata ) {
	if ( !empty($skipwhenempty) && empty($itemdata[$skipwhenempty]) ) {
		continue;
	}

	// set empty string to null
	foreach ( $itemdata  as $k => $v ) {
		if ( $v === "" ) {
			$itemdata[$k] = null;
		}
	}

	$fn = "check_$t";
	if ( function_exists($fn) ) {
		$CHECKER_ERRORS = array();
		$itemdata = $fn($itemdata, $keydata[$i]);
		if ( count($CHECKER_ERRORS) ) {
			error("Errors while processing $t " .
				implode(', ', @$keydata[$i]) . ":\n" .
				implode(";\n", $CHECKER_ERRORS));
		}

	}
	check_sane_keys($itemdata);

	if ( $cmd == 'add' ) {
		$newid = $DB->q("RETURNID INSERT INTO $t SET %S", $itemdata);
		foreach($KEYS[$t] as $tablekey) {
			if ( isset($itemdata[$tablekey]) ) {
				$newid = $itemdata[$tablekey];
			}
		}
	} elseif ( $cmd == 'edit' ) {
		foreach($KEYS[$t] as $tablekey) {
				$prikey[$tablekey] = $keydata[$i][$tablekey];
		}
		check_sane_keys($prikey);

		$DB->q("UPDATE $t SET %S WHERE %S", $itemdata, $prikey);
	}
}

// Throw the user back to the page he came from, if not available
// to the overview for the edited data.
if ( !empty($referrer) ) {
	$returnto = $referrer;
} else {
	$returnto = ($t == 'team_category' ? 'team_categories' : $t.'s'). '.php';
}

header('Location: '.getBaseURI().'jury/'.$returnto);

/**
 * Check an array with field->value data to make sure there's no
 * strange characters in the field name, so we can use that safely
 * in a SQL query.
 */
function check_sane_keys($itemdata) {
	foreach(array_keys($itemdata) as $key) {
		if ( ! preg_match ('/^\w+$/', $key ) ) {
			error ("Invalid characters in field name \"$key\".");
		}
	}
}
