<?php
/**
 * Functions that will check a given row of a given table
 * for problems, and if necessary, normalise it.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

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
	// check the IP address. If it's in a known good format, leave it
	// that way. If not, it may be a hostname, see if we can resolve it.
	// Otherwise error.
	if ( !empty($data['ipaddress']) ) {
		$data['ipaddress'] = trim($data['ipaddress']);
		$ip2l = ip2long($data['ipaddress']);
		// IPv4?
		if ( $ip2l === FALSE ) {
			if ( 		
				// IPv6? PHP >= 5.1 (!Windows)
			 (  function_exists('inet_pton') && !@inet_pton($data['ipaddress']) )
			  // cheap way to guess if it's an IPv6 address
			  && strpos(':', $data['ipaddress']) === FALSE
			) {
				$ip = gethostbyname($data['ipaddress']);
				if ( $ip == $data['ipaddress'] ) {
					ch_error("Cannot get IP address for '" . $ip ."'");
				} else {
					$data['ipaddress'] = $ip;
				}
			}
		}
		// lookup hostname for in hostname cache field;
		// if result is the same as IP address, the lookup failed.
		$host = gethostbyaddr($data['ipaddress']);
		if ( $host != $data['ipaddress'] ) {
			$data['hostname'] = $host;
		} else {
			$data['hostname'] = '';
		}

		if(!$keydata) {
			$keydata['login'] = $data['login'];
		}
		global $DB;
		$team = $DB->q("MAYBEVALUE SELECT `login` FROM `team`"
					. " WHERE `ipaddress` = %s"
					, $data['ipaddress']);
		if($team && $team != $keydata['login']) {
			ch_error("IP address '".$data['ipaddress']."' already in use "
					."by team '".$team."'");
		}
	}
	
	return $data;
}

function check_problem($data, $keydata = null)
{
	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] < 0 ||
			(int)$data['timelimit'] != $data['timelimit'] ) {
		ch_error("Timelimit is not a valid positive integer");
	}
	return $data;
}

function check_language($data, $keydata = null)
{
	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] < 0 ) {
		ch_error("Timelimit is not a valid positive factor");
	}
	if ( strpos($data['extension'], '.') !== FALSE ) {
		ch_error("Do not include the dot (.) in the extension");
	}

	$langs = preg_split("/\s+/", LANG_EXTS);
	$langfound = FALSE;
	foreach ($langs as $lang) {
		$langdata = explode(',', $lang);
		if ( $langdata[1] == $data['extension'] ) {
			$langfound = TRUE;
		}
	}
	if ( !$langfound ) {
		ch_error("Language extension not found in LANG_EXTS from global.cfg");
	}

	return $data;
}

function check_contest($data, $keydata = null)
{
	// are these dates valid?
	foreach(array('starttime','endtime','freezetime',
		'unfreezetime','activatetime') as $f) {
		if ( !empty($data[$f]) ) {
			check_datetime($data[$f]);
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
