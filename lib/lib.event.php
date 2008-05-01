<?php
/**
 * Event system library functions for daemon and plugins.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('FIELD_SEP', '|');

/**
 * Encodes a string for output with URL-like encoding.
 * Don't use PHP 'urlencode' here because it encodes too much.
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
	return urldecode($str);
}

/**
 * Encodes an array of strings with field data to a line of output.
 */
function encode_line($data)
{
	foreach( $data AS $i => $str ) $data[$i] = encode_field($str);

	return implode(FIELD_SEP, $data);
}

/**
 * Decodes a line of input to an array of strings with field data.
 */
function decode_line($line)
{
	$data = explode(FIELD_SEP, $line);

	foreach( $data AS $i => $str ) $data[$i] = decode_field($str);

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

	return decode_line(rtrim($line,"\r\n\0"));
}
