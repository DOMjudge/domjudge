<?php
/**
 * Functions that will check a given row of a given table
 * for problems, and if necessary, normalise it.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('ID_REGEX', '/^' . IDENTIFIER_CHARS . '+$/');

/**
 * Store an error from the checker functions below.
 */
function ch_error($string)
{
	global $CHECKER_ERRORS;
	$CHECKER_ERRORS[] = $string;
}

function check_team($data, $keydata = null)
{
	$id = (isset($data['login']) ? $data['login'] : $keydata['login']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Team ID (login) may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	return $data;
}

function check_affiliation($data, $keydata = null)
{
	$id = (isset($data['affilid']) ? $data['affilid'] : $keydata['affilid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Team affiliation ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	$affillogo = '../images/affiliations/' . urlencode($id) . '.png';
	if ( ! file_exists ( $affillogo ) ) {
		ch_error("Affiliation " . $id .
		         " does not have a logo (looking for $affillogo).");
	} elseif ( ! is_readable ( $affillogo ) ) {
		ch_error("Affiliation " . $data['affilid'] .
		         " has a logo, but it's not readable ($affillogo).");
	}
	return $data;
}

function check_problem($data, $keydata = null)
{
	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] < 0 ||
			(int)$data['timelimit'] != $data['timelimit'] ) {
		ch_error("Timelimit is not a valid positive integer");
	}
	$id = (isset($data['probid']) ? $data['probid'] : $keydata['probid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Problem ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	return $data;
}

function check_language($data, $keydata = null)
{
	global $langexts;

	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] < 0 ) {
		ch_error("Timelimit is not a valid positive factor");
	}
	$id = (isset($data['langid']) ? $data['langid'] : $keydata['langid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Language ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}

	if ( @$langexts[$id]!=$id ) {
		ch_error("Language ID/extension not found or set incorrectly " .
		         "in LANG_EXTS from domserver-config.php");
	}

	return $data;
}

function check_relative_time($time, $starttime, $field)
{
	if ($time[0] == '+' || $time[0] == '-') {
		// convert relative times to absolute ones
		$neg = ($time[0] == '-');
		$time[0] = '0';
		$times = explode(':', $time, 3);
		if ( count($times) == 2 ) $times[2] = 0;
		if ( count($times) == 3 &&
		     is_numeric($times[0]) &&
		     is_numeric($times[1]) && $times[1] < 60 &&
		     is_numeric($times[2]) && $times[2] < 60 ) {
			$hours = $times[0];
			$minutes = $times[1];
			$seconds = 60 * ($minutes + 60 * $hours);
			if ($neg) {
				$seconds *= -1;
			}
			$ret = strftime(MYSQL_DATETIME_FORMAT, strtotime($starttime) + $seconds);
		} else {
			ch_error($field . " is not correctly formatted, expecting: +/-hh:mm");
			$ret = null;
		}
	} else {
		// time is absolute, just copy
		$ret = $time;
	}

	return $ret;
}

function check_contest($data, $keydata = null)
{
	// are these dates valid?
	foreach(array('starttime','endtime','freezetime',
		'unfreezetime','activatetime') as $f) {
		if ($f != 'starttime') {
			// The true input date/time strings are preserved in the
			// *_string variables, since these may be relative times
			// that need to be kept as is.
			$data[$f] = $data[$f.'_string'];
			$data[$f] = check_relative_time($data[$f], $data['starttime'], $f);
		}
		if ( !empty($data[$f]) ) {
			check_datetime($data[$f]);
		}
	}

	// are required times specified?
	foreach(array('activatetime','starttime','endtime') as $f) {
		if ( empty($data[$f]) ) {
			ch_error("Contest $f is empty");
			return $data;
		}
	}

	// the ordering of times is:
	// activatetime <= starttime <= freezetime < endtime <= unfreezetime

	// are contest start/end times in order?
	if ( difftime($data['endtime'], $data['starttime']) <= 0 ) {
		ch_error('Contest ends before it even starts');
	}
	if ( !empty($data['freezetime']) ) {
		if ( difftime($data['freezetime'], $data['endtime']) > 0 ||
		     difftime($data['freezetime'], $data['starttime']) < 0 ) {
			ch_error('Freezetime is out of start/endtime range');
		}
	}
	if ( difftime($data['activatetime'], $data['starttime']) > 0 ) {
		ch_error('Activate time is later than starttime');
	}
	if ( !empty($data['unfreezetime']) ) {
		if ( empty($data['freezetime']) ) {
			ch_error('Unfreezetime set but no freeze time. That makes no sense.');
		}
		if ( difftime($data['unfreezetime'], $data['endtime']) < 0 ) {
			ch_error('Unfreezetime must be larger than endtime.');
		}
	}

	// a check whether this contest overlaps in time with any other, the
	// system can only deal with exactly ONE current contest at any time.
	// A new contest N overlaps with an existing contest E if the activate- or
	// end time or N is inside E (N is (partially) contained in E), or if
	// the activatetime is before E and the end time after E (E is completely
	// contained in N).
	if ( $data['enabled'] ) {
		global $DB;
		$overlaps = $DB->q('COLUMN SELECT cid FROM contest WHERE
	                        enabled = 1 AND
		                    ( (%s >= activatetime AND %s <= endtime) OR
		                      (%s >= activatetime AND %s <= endtime) OR
		                      (%s <= activatetime AND %s >= endtime) ) ' .
		                   (isset($keydata['cid'])?'AND cid != %i ':'%_') .
		                   'ORDER BY cid',
		                   $data['activatetime'], $data['activatetime'],
		                   $data['endtime'], $data['endtime'],
		                   $data['activatetime'], $data['endtime'],
		                   @$keydata['cid']);

		if(count($overlaps) > 0) {
			ch_error('This contest overlaps with the following contest(s): c' .
			         implode(',c', $overlaps));
		}
	}

	return $data;
}

/**
 * Check whether a string is in a valid datetime format, e.g.:
 * 2001-05-12 13:45:00.
 * Checks for the presence of the right parts, and whether the
 * date is sensible (e.g. not 31 February)
 */
function check_datetime($datetime)
{
	$datetime = trim($datetime);

	// It must be 19 chars or we're wrong anyway.
	if (strlen($datetime) != 19) {
		ch_error ("Cannot parse date, not length 19: " . $datetime);
	}
	$y = substr($datetime, 0, 4);
	$m = substr($datetime, 5, 2);
	$d = substr($datetime, 8, 2);
	$hr = substr($datetime, 11, 2);
	$mi = substr($datetime, 14, 2);
	$se = substr($datetime, 17, 2);

	// Is this a valid date?
	if (is_numeric($y) && is_numeric($m) && is_numeric($d) &&
		is_numeric($hr) && is_numeric($mi) && is_numeric($se)) {
		// They are numeric.

		// is this a sensible date?
		$valid = checkdate($m,$d,$y);
		if (!$valid) {
			ch_error ("Cannot parse date, not a valid date: " . $datetime);
		}

		if ( $hr < 0 || $hr > 23 ) {
			ch_error ("Cannot parse date, invalid hour: " . $datetime);
		}
		if ( $mi < 0 || $mi > 59 ) {
			ch_error ("Cannot parse date, invalid minute: " . $datetime);
		}
		if ( $se < 0 || $se > 59 ) {
			ch_error ("Cannot parse date, invalid second: " . $datetime);
		}
	} else {
		ch_error ("Cannot parse date, params not numeric: " . $datetime);
	}

	return $datetime;
}

function check_submission($data, $keydata = null)
{
	check_datetime($data['submittime']);

	return $data;
}

function check_judging($data, $keydata = null)
{
	foreach(array('starttime','endtime') as $f) {
		if ( !empty($data[$f]) ) {
			check_datetime($data[$f]);
		}
	}

	if ( !empty($data['endtime']) && difftime($data['endtime'], $data['starttime']) < 0 ) {
		ch_error('Judging ended before it started');
	}
	if ( !empty($data['submittime']) && difftime($data['starttime'], $data['submittime']) < 0) {
		ch_error('Judging started before it was submitted (clocks unsynched?)');
	}

	return $data;
}
