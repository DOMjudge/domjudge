<?php
/**
 * Functions that will check a given row of a given table
 * for problems, and if necessary, normalise it.
 *
 * $Id$
 */

function check_problem($data, $keydata = null)
{
	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] < 0 ||
			(int)$data['timelimit'] != $data['timelimit'] ) {
		error("Timelimit is not a valid positive integer!");
	}
	return $data;
}

function check_language($data, $keydata = null)
{
	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] < 0 ) {
		error("Timelimit is not a valid positive factor!");
	}
	if ( strpos($data['extension'], '.') !== FALSE ) {
		error("Do not include the dot (.) in the extension!");
	}
	return $data;
}

function check_contest($data, $keydata = null)
{
	if ( empty($data['lastscoreupdate']) ) $data['lastscoreupdate'] = null;
	if ( empty($data['unfreezetime']) ) $data['unfreezetime'] = null;

	// are these dates valid?
	foreach(array('starttime','endtime','lastscoreupdate','unfreezetime') as $f) {
		if ( !empty($data[$f]) ) {
			check_datetime($data[$f]);
		}
	}

	// are contest start/end times in order?
	if($data['endtime'] <= $data['starttime']) {
		error('Contest ends before it even starts!');
	}
	if(isset($data['lastscoreupdate']) &&
		($data['lastscoreupdate'] > $data['endtime'] ||
		$data['lastscoreupdate'] < $data['starttime'] ) ) {
		error('Lastscoreupdate is out of start/endtime range!');
	}
	if ( isset($data['unfreezetime']) ) {
		if ( !isset($data['lastscoreupdate']) ) {
			error('Unfreezetime set but no freeze time. That makes no sense.');
		}
		if ( $data['unfreezetime'] < $data['lastscoreupdate'] ||
			$data['unfreezetime'] < $data['starttime'] ||
			$data['unfreezetime'] < $data['endtime'] ) {
			error('Unfreezetime must be larger than any of start/end/freezetimes.');
		}
	}

	// a check whether this contest overlaps in time with any other, the
	// system can only deal with exactly ONE current contest at any time.
	// A new contest N overlaps with an existing contest E if the start- or
	// end time or N is inside E (N is (partially) contained in E), or if
	// the starttime is before E and the end time after E (E is completely
	// contained in N).
	global $DB;
	$overlaps = $DB->q('COLUMN SELECT cid FROM contest WHERE
	                    ( (%s >= starttime AND %s <= endtime) OR
	                      (%s >= starttime AND %s <= endtime) OR
			      (%s <= starttime AND %s >= endtime)
			    ) ' .
			    (isset($keydata['cid'])?'AND cid != %i ':'%_') .
			    'ORDER BY cid',
	                   $data['starttime'], $data['starttime'],
			   $data['endtime'], $data['endtime'],
			   $data['starttime'], $data['endtime'],
			   @$keydata['cid']);
	
	if(count($overlaps) > 0) {
		error('This contest overlaps with the following contest(s): c' . 
			htmlspecialchars(implode(',c', $overlaps)));
	}
	
	return $data;
}

/**
 * Check whether a string is in a valid datetime format, e.g.:
 * 2001-05-12 13:45:00.
 * Checks for the presence of the right parts, and whether the
 * date is sensible (e.g. not 31 February)
 */
function check_datetime($datetime) {
	$datetime = trim($datetime);

	// It must be 19 chars or we're wrong anyway.
	if (strlen($datetime) != 19) {
		error ("Cannot parse date, not length 19: " . htmlspecialchars($datetime));
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
			error ("Cannot parse date, not a valid date: " . htmlspecialchars($datetime));
		}

		if ( $hr < 0 || $hr > 23 ) {
			error ("Cannot parse date, invalid hour: " . htmlspecialchars($datetime));
		}
		if ( $mi < 0 || $mi > 59 ) {
			error ("Cannot parse date, invalid minute: " . htmlspecialchars($datetime));
		}
		if ( $se < 0 || $se > 59 ) {
			error ("Cannot parse date, invalid second: " . htmlspecialchars($datetime));
		}
	} else {
		error ("Cannot parse date, params not numeric: " . htmlspecialchars($datetime));
	}	
	
	return $datetime;
}
