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

require(LIBDIR .  '/relations.php');

$t = @$_POST['table'];
if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$data          =  $_POST['data'];
$keydata       = @$_POST['keydata'];
$skipwhenempty = @$_POST['skipwhenempty'];
$referrer      = @$_POST['referrer'];

if ( empty($data) ) error ("No data.");

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

		if ( !empty($_FILES['data']['name'][$i]['archive']) ) {
			checkFileUpload( $_FILES['data']['error'][$i]['archive'] );
			$zip = openZipFile($_FILES['data']['tmp_name'][$i]['archive']);

			# update testcases
			$maxrank = 1 + $DB->q('VALUE SELECT max(rank)
                                               FROM testcase WHERE probid = %s',
					       $keydata[$i]['probid']);
			for ($j = 0; $j < $zip->numFiles; $j++) {  
				$filename = $zip->getNameIndex($j);
				if ( ends_with($filename, ".in") ) {
					$basename = basename($filename, ".in");
					$fileout = $basename . ".out";
					$testout = $zip->getFromName($fileout);
					if ($testout !== FALSE) {
						$testin = $zip->getFromIndex($j);

						$DB->q('INSERT INTO testcase
							(probid,rank,md5sum_input,
							md5sum_output,input,output,
							description)
						        VALUES (%s,%i,%s,%s,%s,%s,%s)',
							$keydata[$i]['probid'],
							$maxrank,
							md5($testin), md5($testout),
							$testin, $testout, $basename);
						$maxrank++;
					}
				}
			} 

			# update problem properties
			$properties = $zip->getFromName("properties.ini");
			if ($properties !== FALSE) {
				$ini_array = parse_ini_string($properties);
				if ($ini_array !== FALSE) {
					$row = $DB->q('TUPLE SELECT * FROM problem
						       WHERE probid = %s',
						      $keydata[$i]['probid']);
					$ini_keys = array('timelimit', 'name',
							'color', 'special_run',
							'special_compare', 'cid',
							'allow_submit', 'allow_judge');
					foreach ($ini_keys as $ini_key) {
						if (isset($ini_array[$ini_key])) {
							$row[$ini_key] = $ini_array[$ini_key];
						}
					}

					$DB->q('UPDATE problem SET timelimit=%i,
						name=%s, color=%s,special_run=%i,
						special_compare=%i, cid=%i,
						allow_submit=%i, allow_judge=%i
						WHERE probid=%s',
						$row['timelimit'], $row['name'],
						$row['color'], $row['special_run'],
						$row['special_compare'], $row['cid'],
						$row['allow_submit'], $row['allow_judge'],
						$keydata[$i]['probid']
					);
				}
			}
				
			$zip->close();
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
