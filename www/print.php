<?php

/**
 * Functions for formatting certain output to the user.
 * All output is HTML-safe, so input should not be escaped.
 *
 * $Id$
 */

/**
 * prints result with correct style, '' -> judging
 */
function printresult($result, $valid = TRUE) {

	$start = '<span class="sol ';
	$end   = '</span>';

	switch($result) {
		case '':
			$result = 'judging';
		case 'judging':
		case 'queued':
			$style = 'queued';
			break;
		case 'correct':
			$style = 'correct';
			break;
		default:
			$style = 'incorrect';
	}

	return $start . ($valid ? $style : 'disabled') . '">' . $result . $end;

}

/**
 * print a yes/no field, input: something that evaluates to a boolean
 */
function printyn ($val) {
	return ($val ? '1':'0');
}

/**
 * given 2004-12-31 15:43:05, returns 15:43:05
 */
function printtime($datetime) {
	if(!$datetime) return '';
	$date_time = explode(' ',$datetime);
	return htmlspecialchars($date_time[1]);

}

/**
 * Formats a given hostname. If $full = true, then
 * the full hostname will be printed, else only
 * the local part (for keeping tables readable)
 */
function printhost($hostname, $full = FALSE) {
	if(!$full) {
		$hostname = array_shift(explode('.', $hostname));
	}

	return "<span class=\"hostname\">".htmlspecialchars($hostname)."</span>";
}
