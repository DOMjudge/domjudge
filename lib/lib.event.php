<?php
/**
 * Event system library functions for daemon and plugins.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('FIELD_SEP', '|');
define('RECORD_SEP', '\n');

/**
 * Encodes a string for output with URL-like encoding.
 * Don't use PHP 'rawurlencode' here because it encodes all
 * non-alphanumeric characters.
 */
function encode_field($str)
{
	$res = '';
	for($i=0; $i<strlen($str); $i++) {

		$n = ord($str[$i]);
		if ( $n < 32 || $n > 126 || $str[$i]==FIELD_SEP || $str[$i]=='%' ) {
			$res .= sprintf('%%%02X', $n);
		} else {
			$res .= $str[$i];
		}
	}

	return $res;
}

/**
 * Decodes a URL-like encoded string.
 */
function decode_field($str)
{
	return rawurldecode($str);
}

/**
 * Encodes an array of strings with field data to a line of output.
 */
function encode_line($data)
{
	return implode(FIELD_SEP, array_map("encode_field", $data)) . RECORD_SEP;
}

/**
 * Decodes a line of input to an array of strings with field data.
 */
function decode_line($line)
{
	$data = array_map("decode_field", explode(FIELD_SEP, $line));

	// Add text keys for default fields
	$data['description'] = $data[0];
	$data['timestamp']   = $data[1];
	$data['eventid']     = $data[2];
	$data['cid']         = $data[3];

	return $data;
}

/**
 * Reads a line from stdin, then decodes it. Returns false on EOF.
 */
function read_decode_line()
{
	if ( feof(STDIN) || !($line = fgets(STDIN)) ) return FALSE;

	// Here we strip trailing linefeed and carriage return characters
	// and ignore the RECORD_SEP (assumed to be '\n').
	return decode_line(rtrim($line,"\r\n\0"));
}
