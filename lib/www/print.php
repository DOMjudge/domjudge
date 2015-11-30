<?php

/**
 * Functions for formatting certain output to the user.
 * All output is HTML-safe, so input should not be escaped.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * prints result with correct style, '' -> judging
 */
function printresult($result, $valid = TRUE) {

	$start = '<span class="sol ';
	$end   = '</span>';

	switch( $result ) {
		case 'too-late':
			$style = 'sol_queued';
			break;
		case '':
			$result = 'judging';
		case 'judging':
		case 'queued':
			if ( ! IS_JURY ) $result = 'pending';
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
 * Print a time in default configured time_format, or formatted as
 * specified. The format is according to strftime().
 * If $cid is specified, print time relative to that contest start.
 */
function printtime($datetime, $format = NULL, $cid = NULL) {
	if ( empty($datetime) ) return '';
	if ( is_null($format) ) $format = dbconfig_get('time_format', '%H:%M');
	if ( isset($cid) ) {
	        $reltime = (int)floor(calcContestTime($datetime,$cid));
		$sign = ( $reltime<0 ? -1 : 1 );
		$reltime *= $sign;
		// We're not showing seconds, while the last minute before
		// contest start should show as "-0:01", so if there's a
		// nonzero amount of seconds before the contest, we have to
		// add a minute.
		$s = $reltime%60; $reltime = ($reltime - $s)/60;
		if ( $sign<0 && $s>0 ) $reltime++;
		$m = $reltime%60; $reltime = ($reltime - $m)/60;
		$h = $reltime;
		if ( $sign<0 ) {
			return sprintf("-%d:%02d", $h, $m);
		} else {
			return sprintf("%d:%02d", $h, $m);
		}
	} else {
		return specialchars(strftime($format,floor($datetime)));
	}
}

/**
 * Formats a given hostname. If $full = true, then
 * the full hostname will be printed, else only
 * the local part (for keeping tables readable)
 */
function printhost($hostname, $full = FALSE) {
	// Shorten the hostname to first label, but not if it's an IP address.
	if( ! $full  && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname) ) {
		$expl = explode('.', $hostname);
		$hostname = array_shift($expl);
	}

	return "<span class=\"hostname\">".specialchars($hostname)."</span>";
}

/**
 * Print the time something took from start to end (which defaults to now).
 */
function printtimediff($start, $end = NULL)
{
	if ( is_null($end) ) $end = microtime(TRUE);
	$ret = '';
	$diff = floor($end - $start);

	if ( $diff >= 24*60*60 ) {
		$d = floor($diff/(24*60*60));
		$ret .= $d . "d ";
		$diff -= $d * 24*60*60;
	}
	if ( $diff >= 60*60 || isset($d) ) {
		$h = floor($diff/(60*60));
		$ret .= $h . ":";
		$diff -= $h * 60*60;
	}
	$m = floor($diff/60);
	$ret .= sprintf('%02d:', $m);
	$diff -= $m * 60;
	$ret .= sprintf('%02d', $diff);

	return $ret;
}

/**
 * Print (file) size in human readable format by using B,KB,MB,GB suffixes.
 * Input is a integer (the size in bytes), output a string with suffix.
 */
function printsize($size, $decimals = 1)
{
	$factor = 1024;
	$units = array('B', 'KB', 'MB', 'GB');
	$display = (int)$size;

	for ($i = 0; $i < count($units) && $display > $factor; $i++) {
		$display /= $factor;
	}

	if ( $i==0 ) $decimals = 0;
	return sprintf("%.${decimals}lf&nbsp;%s", $display, $units[$i]);
}

/**
 * Print the relative time in h:mm:ss[.uuuuuu] format.
 */
function printtimerel($rel_time, $use_microseconds = FALSE)
{
	if ( $use_microseconds ) {
		$frac_str = explode('.', sprintf('%.6f', $rel_time))[1];
	}
	$rel_time = (int) floor($rel_time);

	$h = floor($rel_time/3600);
	$rel_time %= 3600;

	$m = floor($rel_time/60);
	if ($m < 10) {
		$m = '0' . $m;
	}
	$rel_time %= 60;

	$s = $rel_time;
	if ($s < 10) {
		$s = '0' . $s;
	}

	if ( $use_microseconds ) {
		$s .= '.' . $frac_str;
	}

	return $h . ':' . $m . ':' . $s;
}

/**
 * Cut a string at $size chars and append ..., only if neccessary.
 */
function str_cut ($str, $size) {
	// is the string already short enough?
	// we count '…' for 1 extra chars.
	if( mb_strlen($str) <= $size+1 ) {
		return $str;
	}

	return mb_substr($str, 0, $size) . '…';
}

/**
 * Output an html "message box"
 *
 */
function msgbox($caption, $message) {
	return "<fieldset class=\"msgbox\"><legend>" .
		"<img src=\"../images/huh.png\" class=\"picto\" alt=\"?\" /> " .
		$caption . "</legend>\n" .
		$message .
		"</fieldset>\n\n";
}
