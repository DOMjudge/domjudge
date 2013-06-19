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
 * print a time dependent on configured time_format.
 * if $contesttime is set, show time from start of contest, after
 * removing ignored intervals.
 */
function printtime($datetime, $contesttime = FALSE) {
	if ( ! $datetime ) return '';
	if ( $contesttime ) {
		$reltime = (int)floor(calcContestTime($datetime));
		$sign = ( $reltime<0 ? -1 : 1 );
		$reltime *= $sign;
		$s = $reltime%60; $reltime = ($reltime - $s)/60;
		$m = $reltime%60; $reltime = ($reltime - $m)/60;
		$h = $reltime;
		// The last minute before contest start should show as "-00:01"
		if ( $sign<0 ) {
			return sprintf("-%d:%02d", $h, $m+1);
		} else {
			return sprintf("%d:%02d", $h, $m);
		}
	} else {
		return htmlspecialchars(date(dbconfig_get('time_format', 'H:i'), strtotime($datetime)));
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
 * print the relative time in h:mm:ss format
 */
function printtimerel($rel_time) {

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
