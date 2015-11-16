<?php
/**
 * Miscellaneous wrappers for PHP functions, included from lib.misc.php.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Wrapper around PHP setcookie function to automatically set some
 * DOMjudge specific defaults and check the return value.
 * - cookies are defined in a common path for all web interfaces
 */
function dj_setcookie($name, $value = null, $expire = 0,
                      $path = null, $domain = null, $secure = false, $httponly = false)
{
	if ( !isset($path) ) {
		// KLUDGE: We want to find the DOMjudge base path, but this
		// information is not directly available as configuration, so
		// we extract it from the executed PHP script.
		$path = preg_replace('/(jury|public|team)\/?$/', '',
		                     dirname($_SERVER['PHP_SELF']));
	}

	$ret = setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);

	if ( $ret!==true ) warning("Cookie '$name' not set properly.");

	logmsg(LOG_DEBUG, "Cookie set: $name=$value, expire=$expire, path=$path");

	return $ret;
}

/**
 * Decode a json encoded string and handle errors.
 */
function dj_json_decode($str) {
	$res = json_decode($str, TRUE);
	if ( $res === NULL ) {
		error("Error retrieving API data. API gave us: " . $str);
	}
	return $res;
}

/**
 * helperfunction to read all contents from a file.
 * If $sizelimit is true (default), then only limit this to
 * the first 50,000 bytes and attach a note saying so.
 */
function dj_get_file_contents($filename, $sizelimit = true) {

	if ( ! file_exists($filename) ) {
		return '';
	}
	if ( ! is_readable($filename) ) {
		error("Could not open $filename for reading: not readable");
	}

	if ( $sizelimit && filesize($filename) > 50000 ) {
		return file_get_contents($filename, FALSE, NULL, -1, 50000)
			. "\n[output truncated after 50,000 B]\n";
	}

	return file_get_contents($filename);
}

