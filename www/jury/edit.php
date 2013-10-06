<?php
/**
 * Functionality to edit data from this interface.
 *
 * TODO:
 *  - Does not support checkboxes yet, since these
 *    return no value when not checked.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

$cmd = @$_POST['cmd'];
if ( $cmd != 'add' && $cmd != 'edit' ) error ("Unknown action.");

require(LIBDIR .  '/relations.php');

$t = @$_POST['table'];
if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$data          =  $_POST['data'];
$keydata       = @$_POST['keydata'];
$skipwhenempty = @$_POST['skipwhenempty'];
$referrer      = @$_POST['referrer'];

if ( empty($data) ) error ("No data.");
// ensure referrer only contains a single filename, not complete URLs
if ( ! preg_match('/^[._a-zA-Z0-9?&=]*$/', $referrer ) ) error ("Invalid characters in referrer.");

require(LIBWWWDIR . '/checkers.jury.php');

if ( ! isset($_POST['cancel']) ) {
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

		// special case for many-to-many mappings
		$mappingdata = null;
		if ( is_array($itemdata['mapping']) ) {
			$mappingdata = $itemdata['mapping'];
			unset($itemdata['mapping']);
		}

		$fn = "check_$t";
		if ( function_exists($fn) ) {
			$CHECKER_ERRORS = array();
			$itemdata = $fn($itemdata, $keydata[$i]);
			if ( count($CHECKER_ERRORS) ) {
				error("Errors while processing $t " .
					@implode(', ', @$keydata[$i]) . ":\n" .
					implode(";\n", $CHECKER_ERRORS));
			}

		}
		check_sane_keys($itemdata);

		$newid = null;
		if ( $cmd == 'add' ) {
			$newid = $DB->q("RETURNID INSERT INTO $t SET %S", $itemdata);
			auditlog($t, $newid, 'added');

			$i = 0;
			// save the primary key for the insert
			foreach($KEYS[$t] as $tablekey) {
				if ( $i == 0 ) { // Assume first primary key is the autoincrement one
					$prikey[$tablekey] = $newid;
				}
				if ( isset($itemdata[$tablekey]) ) {
					$prikey[$tablekey] = $itemdata[$tablekey];
				}
				$i++;
			}
		} elseif ( $cmd == 'edit' ) {
			foreach($KEYS[$t] as $tablekey) {
					$prikey[$tablekey] = $keydata[$i][$tablekey];
			}
			check_sane_keys($prikey);

			$DB->q("UPDATE $t SET %S WHERE %S", $itemdata, $prikey);
			auditlog($t, implode(', ', $prikey), 'updated');
		}

		// special case for many-to-many mappings
		if ( $mappingdata != null ) {
			$junctiontable = $mappingdata['table'];
			$fk = $mappingdata['fk'];

			// Make sure this is a valid mapping
			check_manymany_mapping($junctiontable, $fk);

			// Remove all old mappings
			$DB->q('DELETE FROM %l WHERE %S', $junctiontable, $prikey);
			foreach ($mappingdata['items'] as $mapdest) {
				$ret = $DB->q('INSERT INTO %l (%l, %l) VALUES (%s,%s)',
				              $junctiontable, $fk[0], $fk[1], $prikey[$fk[0]], $mapdest);
			}
		}
	}
	// If the form contained uploadable files, process these now.
	if ( isset($_FILES['data']) ) {
		foreach($_FILES['data']['tmp_name'] as $id => $tmpnames) {
			foreach($tmpnames as $field => $tmpname) {
				if ( !empty ($tmpname) ) {
					checkFileUpload($_FILES['data']['error'][$id][$field]);
					$itemdata = array($field => file_get_contents($tmpname));
					$DB->q("UPDATE $t SET %S WHERE %S", $itemdata, $prikey);
				}
			}
		}
	}
}

// Throw the user back to the page he came from, if not available
// to the overview for the edited data.
if ( !empty($referrer) ) {
	$returnto = $referrer;
} else {
	$returnto = ($t == 'team_category' ? 'team_categories' : $t.'s'). '.php';
}

header('Location: '.$returnto);

/**
 * Check an array with field->value data to make sure there's no
 * strange characters in the field name, so we can use that safely
 * in a SQL query.
 */
function check_sane_keys($itemdata) {
	foreach(array_keys($itemdata) as $key) {
		if ( ! preg_match ('/^' . IDENTIFIER_CHARS . '+$/', $key ) ) {
			error ("Invalid characters in field name \"$key\".");
		}
	}
}

// Verify a many-to-many mapping is valid
function check_manymany_mapping($table, $keys) {
	if ( ! preg_match ('/^' . IDENTIFIER_CHARS . '+$/', $table ) ) {
		error ("Invalid characters in table name \"$table\".");
	}

	global $KEYS;
	foreach($keys as $key) {
		if (!in_array($key, $KEYS[$table])) {
			error("Invalid many-to-many mapping.");
		}

		if ( ! preg_match ('/^' . IDENTIFIER_CHARS . '+$/', $key ) ) {
			error ("Invalid characters in field name \"$key\".");
		}
	}
}
