<?php
/**
 * View/edit configration settings.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

requireAdmin();

dbconfig_init();

if ( isset($_POST['save']) ) {

	foreach ( $_POST as $tmp => $val ) {
		if ( substr($tmp, 0, 7)!='config_' ) continue;
		$key = substr($tmp, 7);

		if ( !isset($LIBDBCONFIG[$key]) ) {
			error("Cannot set unknown configuration variable '$key'");
		}

		switch ( $LIBDBCONFIG[$key]['type'] ) {
		case 'bool':
			$val = (bool)$val ? 1 : 0;
			break;
		case 'int':
			$val = (int)$val;
			break;
		case 'array_val':
			$res = array();
			foreach ( $val as $data ) {
				if ( !empty($data) ) $res[] = $data;
			}
			$val = $res;
			break;
		case 'array_keyval':
			$res = array();
			foreach ( $val as $data ) {
				if ( !empty($data['key']) ) $res[$data['key']] = $data['val'];
			}
			$val = $res;
			break;
		}

		$LIBDBCONFIG[$key]['value'] = $val;
	}

	dbconfig_store();

	// Redirect to the original page to prevent accidental redo's
	header('Location: config.php');
	return;
}

$title = "Configuration";
require(LIBWWWDIR . '/header.php');

echo "<h1>Configuration settings</h1>\n\n";

echo addForm('config.php') . "<table>\n<thead>\n" .
    "<tr align=\"left\"><th>name</th><th>value(s)</th><th>description</th></tr>\n" .
    "</thead>\n<tbody>\n";

foreach ( $LIBDBCONFIG as $key => $data ) {
	switch ( @$data['type'] ) {
	case 'bool':
		$editfield =
		    addRadioButton('config_'.$key, (bool)$data['value']==true, 1) .
		    "<label for=\"config_${key}1\">yes</label>" .
		    addRadioButton('config_'.$key, (bool)$data['value']==false, 0) .
		    "<label for=\"config_${key}0\">no</label>";
		break;
	case 'int':
		$editfield = addInput('config_'.$key, $data['value'], 10, 10);
		break;
	case 'string':
		$editfield = addInput('config_'.$key, $data['value'], 30);
		break;
	case 'array_val':
	case 'array_keyval':
		$editfield = '';
		$i = 0;
		foreach ( $data['value'] as $k => $v ) {
			if ( $data['type']=='array_keyval' ) {
				$editfield .= addInput("config_${key}[$i][key]", $k, 10);
				$editfield .= addInput("config_${key}[$i][val]", $v, 18);
			} else {
				$editfield .= addInput("config_${key}[$i]", $v, 30);
			}
			$editfield .= "<br />";
			$i++;
		}
		if ( $data['type']=='array_keyval' ) {
			$editfield .= addInput("config_${key}[$i][key]", '', 10);
			$editfield .= addInput("config_${key}[$i][val]", '', 18);
		} else {
			$editfield .= addInput("config_${key}[$i]", '', 30);
		}
		break;
	default:
		$editfield = '';
		break;
	}
	// Ignore unknown datatypes
	if ( empty($editfield) ) continue;

	echo "<tr><td>" . htmlspecialchars($key) .
		"</td><td style=\"white-space: nowrap;\">" . $editfield .
		"</td><td>" . htmlspecialchars($data['desc']) .
		"</td></tr>\n";
}

echo "</tbody>\n</table>\n<p>" .
	addSubmit('Save', 'save') . addSubmit('Cancel', 'cancel') . "</p>" .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
