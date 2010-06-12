<?php
/**
 * Functions for reading runtime configuration files
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Given PHP's implementation of arrays, most of these functions are
 * pretty trivial. We implement them here none the less to keep the
 * interface consistent with the C interface.
 */

$LIBCONFIGOPTIONS = array();

function config_isset($option)
{
	global $LIBCONFIGOPTIONS;

	return isset($LIBCONFIGOPTIONS[$option]);
}

function config_getvalue($option)
{
	global $LIBCONFIGOPTIONS;

	return $LIBCONFIGOPTIONS[$option];
}

function config_setvalue($option, $value)
{
	global $LIBCONFIGOPTIONS;

	$LIBCONFIGOPTIONS[$option] = $value;
}

function config_readfile($filename)
{
	global $LIBCONFIGOPTIONS;

	if ( !($fd = fopen($filename, 'r')) ) error("could not open '$filename'");

	// Read line by line
	$lineno = 0;
	while ( ($line = fgets($fd))!==FALSE ) {
		$lineno++;

		$line = trim($line);

		if ( strlen($line)==0 ) continue; // empty line
		if ( $line[0]==';' || $line[0]=='#' ) continue; // comment line

		// Split on first '=': key/value separator
		$keyval = explode('=', $line, 2);
		if ( count($keyval)!=2 ) {
			error("on line $lineno of '$filename': no key/value pair found");
		}
		list($option, $value) = $keyval;

		$option = trim($option);
		$value  = trim($value);

		// Check option for alphanumeric chars and _
		if ( !preg_match('/^[A-Za-z0-9_]+$/', $option) ) {
			error("on line $lineno of '$filename': illegal key '$option'");
		}

		// Strip optional enclosing quotes from value
		$value = preg_replace('/^"(.*)"$/', "$1", $value);

		config_setvalue($option, $value);
	}

	fclose($fd);
}
