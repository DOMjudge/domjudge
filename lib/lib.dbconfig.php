<?php
/**
 * Functions for handling database stored configuration.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Read configuration variables from DB configuration table and store
 * in global variable for later use.
 */
function dbconfig_init()
{
	global $LIBDBCONFIG, $DB;

	$LIBDBCONFIG = array();
	$res = $DB->q('SELECT * FROM configuration');

	while ( $row = $res->next() ) {
		$key = $row['name'];
		$val = json_decode($row['value'], true);

		// json_last_error() is only available in PHP >= 5.3
		if ( function_exists('json_last_error') ) {
			switch ( json_last_error() ) {
			case JSON_ERROR_NONE:
				break;
			case JSON_ERROR_DEPTH:
				error("JSON config '$key' decode: maximum stack depth exceeded");
			case JSON_ERROR_STATE_MISMATCH:
				error("JSON config '$key' decode: underflow or the modes mismatch");
			case JSON_ERROR_CTRL_CHAR:
				error("JSON config '$key' decode: unexpected control character found");
			case JSON_ERROR_SYNTAX:
				error("JSON config '$key' decode: syntax error, malformed JSON");
			/* Only available since PHP >= 5.3.3:
			case JSON_ERROR_UTF8:
				error("JSON config '$key' decode: malformed UTF-8 characters, possibly incorrectly encoded");
			 */
			default:
				error("JSON config '$key' decode: unknown error");
			}
		} else {
			if ( $val === NULL ) {
				error("JSON config '$key' decode: unknown error");
			}
		}

		switch ( $type = $row['type'] ) {
		case 'bool':
		case 'int':
			if ( !is_int($val) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		case 'string':
			if ( !is_string($val) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		case 'array_val':
		case 'array_keyval':
			if ( !is_array($val) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		default:
			error("unknown type '$type' for config variable '$key'");
		}

		$LIBDBCONFIG[$key] = array('value' => $val,
		                           'type' => $row['type'],
		                           'desc' => $row['description']);
	}
}

/**
 * Store configuration variables to the DB configuration table.
 */
function dbconfig_store()
{
	global $LIBDBCONFIG, $DB;

	foreach ( $LIBDBCONFIG as $key => $row ) {

		switch ( $type = $row['type'] ) {
		case 'bool':
		case 'int':
			if ( !preg_match('/^\s*(-){0,1}[0-9]+\s*$/', $row['value']) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		case 'string':
			if ( !is_string($row['value']) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		case 'array_val':
		case 'array_keyval':
			if ( !is_array($row['value']) ) {
				error("invalid type '$type' for config variable '$key'");
			}
			break;
		default:
			error("unknown type '$type' for config variable '$key'");
		}

		$val = json_encode($row['value']);

		if ( function_exists('json_last_error') ) {
			switch ( json_last_error() ) {
			case JSON_ERROR_NONE:
				break;
			case JSON_ERROR_DEPTH:
				error("JSON config '$key' encode: maximum stack depth exceeded");
			case JSON_ERROR_STATE_MISMATCH:
				error("JSON config '$key' encode: underflow or the modes mismatch");
			case JSON_ERROR_CTRL_CHAR:
				error("JSON config '$key' encode: unexpected control character found");
			case JSON_ERROR_SYNTAX:
				error("JSON config '$key' encode: syntax error, malformed JSON");
			/* Only available since PHP >= 5.3.3:
			case JSON_ERROR_UTF8:
				error("JSON config '$key' encode: malformed UTF-8 characters, possibly incorrectly encoded");
			 */
			default:
				error("JSON config '$key' encode: unknown error");
			}
		} else {
			if ( $val === NULL ) {
				error("JSON config '$key' encode: unknown error");
			}
		}

		$res = $DB->q('RETURNAFFECTED UPDATE configuration
		               SET value = %s, type = %s, description = %s
		               WHERE name = %s', $val, $row['type'], $row['desc'], $key);

		if ( $res>0 ) auditlog('configuration', NULL, 'update '.$key, $val);
	}
}

/**
 * Query configuration variable, with optional default value in case
 * the variable does not exist and boolean to indicate if cached
 * values can be used.
 */
function dbconfig_get($name, $default = null, $cacheok = true)
{
	global $LIBDBCONFIG;

	if ( (!isset($LIBDBCONFIG)) || (!$cacheok) ) {
		dbconfig_init();
	}

	if ( isset($LIBDBCONFIG[$name]) ) return $LIBDBCONFIG[$name]['value'];

	if ( $default===null ) {
		error("Configuration variable '$name' not found.");
	}
	return $default;
}
