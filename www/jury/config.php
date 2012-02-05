<?php
/**
 * View/edit configration settings.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

requireAdmin();

$config = $DB->q('KEYTABLE SELECT name AS ARRAYKEY, c.* FROM configuration c');

if ( isset($_POST['save']) ) {

	foreach ( $_POST as $tmp => $val ) {
		if ( substr($tmp, 0, 7)!='config_' ) continue;
		$key = substr($tmp, 7);

		if ( !isset($config[$key]) ) {
			error("Cannot set unknown configuration variable '$key'");
		}

		// Test data type validity and cast to standard form:
		switch ( @$config[$key]['type'] ) {
		case 'bool':
			$val = ( (bool)$val ? '1' : '0' );
			break;
		case 'int':
			if ( (int)$val!=$val ) {
				error("Configuration variable '$key' must be integer");
			}
			break;
		case 'string':
			// Nothing to do here.
			break;
		default:
			continue 2; // Skip unknown datatypes.
		}

		if ( $config[$key]['value']!=$val ) {
			$DB->q('UPDATE configuration SET value = %s WHERE name = %s',
			       $val, $key);
			auditlog('configuration', NULL, 'update '.$key, $val);
		}
	}

	// Redirect to the original page to prevent accidental redo's
	header('Location: config.php');
	return;
}

$title = "Configuration";
require(LIBWWWDIR . '/header.php');

echo "<h1>Configuration settings</h1>\n\n";

echo addForm('config.php') . "<table>\n<thead>\n" .
    "<tr align=\"left\"><th>name</th><th>value</th><th>description</th></tr>\n" .
    "</thead>\n<tbody>\n";

foreach ( $config as $key => $data ) {
	switch ( @$data['type'] ) {
	case 'bool':
		$editfield =
		    addRadioButton('config_'.$key, (bool)$data['value']==true, 1) .
		    "<label for=\"config_${key}1\">yes</label>" .
		    addRadioButton('config_'.$key, (bool)$data['value']==false, 0) .
		    "<label for=\"config_${key}0\">no</label>";
		break;
	case 'int':
		$editfield = addInput('config_'.$key, $data['value'], 6, 6);
		break;
	case 'string':
		$editfield = addInput('config_'.$key, $data['value'], 30);
		break;
	default:
		$editfield = '';
		break;
	}
	// Ignore unknown datatypes
	if ( empty($editfield) ) continue;

	echo "<tr><td>" . htmlspecialchars($key) .
		"</td><td>" . $editfield .
		"</td><td>" . htmlspecialchars($data['description']) .
		"</td></tr>\n";
}

echo "</tbody>\n</table>\n<p>" .
	addSubmit('Save', 'save') . addSubmit('Cancel', 'cancel') . "</p>" .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
