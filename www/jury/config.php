<?php
/**
 * View/edit configration settings.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

dbconfig_init();

if ( isset($_POST['save']) ) {

	requireAdmin();

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

// Check admin rights after header to generate valid HTML page
requireAdmin();

echo "<h1>Configuration settings</h1>\n\n";

echo addForm($pagename) . "<table>\n<thead>\n" .
    "<tr class=\"thleft\"><th>Option</th><th>Value(s)</th><th>Description</th></tr>\n" .
    "</thead>\n<tbody>\n";

$extra = ' class="config_input"';
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
		$editfield = addInputField('number', 'config_'.$key, $data['value'],$extra);
		break;
	case 'string':
		$editfield = addInput('config_'.$key, $data['value'], 0,0,$extra);
		break;
	case 'array_val':
	case 'array_keyval':
		$editfield = '';
		$i = 0;
		foreach ( $data['value'] as $k => $v ) {
			if ( $data['type']=='array_keyval' ) {
				$editfield .= addInput("config_${key}[$i][key]", $k, 0,0,$extra);
				$editfield .= addInput("config_${key}[$i][val]", $v, 0,0,$extra);
			} else {
				$editfield .= addInput("config_${key}[$i]", $v, 0,0,$extra);
			}
			$editfield .= "<br />";
			$i++;
		}
		if ( $data['type']=='array_keyval' ) {
			$editfield .= addInput("config_${key}[$i][key]", '', 0,0,$extra);
			$editfield .= addInput("config_${key}[$i][val]", '', 0,0,$extra);
		} else {
			$editfield .= addInput("config_${key}[$i]", '', 0,0,$extra);
		}
		break;
	default:
		$editfield = '';
		break;
	}
	// Ignore unknown datatypes
	if ( empty($editfield) ) continue;

	echo "<tr><td>" . htmlspecialchars(ucfirst(strtr($key,'_',' '))) .
		"</td><td style=\"white-space: nowrap;\">" . $editfield .
		"</td><td>" . htmlspecialchars($data['desc']) .
		"</td></tr>\n";
}

echo "</tbody>\n</table>\n<p>" .
	addSubmit('Save', 'save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	"</p>" .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
