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
function printresult($result, $valid = TRUE, $teamstatus = FALSE) {

	$start = '<span class="sol ';
	$end   = '</span>';

	switch( $result ) {
		case '':
			$result = 'judging';
		case 'judging':
		case 'queued':
			if($teamstatus) $result = 'pending';
			$style = 'sol_queued';
			break;
		case 'correct':
			$style = 'sol_correct';
			break;
		default:
			$style = 'sol_incorrect';
	}

	return $start . ($valid ? $style : 'disabled') . '">' . $result . $end;

}

/**
 * print a yes/no field, input: something that evaluates to a boolean
 */
function printyn ($val) {
	return ($val ? 'yes':'no');
}

/**
 * given 2004-12-31 15:43:05, returns 15:43:05
 */
function printtime($datetime) {
	if ( ! $datetime ) return '';
	$date_time = explode(' ',$datetime);
	return htmlspecialchars($date_time[1]);

}

/**
 * Formats a given hostname. If $full = true, then
 * the full hostname will be printed, else only
 * the local part (for keeping tables readable)
 */
function printhost($hostname, $full = FALSE) {
	if( ! $full ) {
		$hostname = array_shift(explode('.', $hostname));
	}

	return "<span class=\"hostname\">".htmlspecialchars($hostname)."</span>";
}

/**
 * print the time something took from start to end.
 * input: timestamps, end defaults to now.
 */
function printtimediff($start, $end = null) {
	
	if( ! $end )	$end = time();
	$ret = '';
	$diff = $end - $start;

	$h = floor($diff/3600);
	$diff %= 3600;
	if($h > 0) {
		$ret .= $h.' h ';
	}
	
	$m = floor($diff/60);
	$diff %= 60;
	if ( $m > 0 ) {
		$ret .= $m.' m ';
	}
	
	return $ret . $diff .' s';
}

/**
 * Cut a string at $size chars and append ..., only if neccessary.
 */
function str_cut ($str, $size) {
	// is the string already short enough?
	// we count '...' for 2 'regular' chars.
	if( strlen($str) <= $size+2 ) {
		return $str;
	}

	return substr($str, 0, $size) . '...';
}
