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

function check_user($data, $keydata = null)
{
	$id = (isset($data['username']) ? $data['username'] : $keydata['username']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Username may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	if ( ! empty($data['email'])  && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
		ch_error("Email not valid.");
	}
	if ( !empty($data['password']) ) {
		$data['password'] = md5("$id#".$data['password']);
	}
	return $data;
}


function check_affiliation($data, $keydata = null)
{
	$id = (isset($data['affilid']) ? $data['affilid'] : $keydata['affilid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Team affiliation ID may only contain characters " . IDENTIFIER_CHARS . ".");
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

	if ( !empty($_FILES['data']['name'][0]['problemtext']) ) {
		$origname = $_FILES['data']['name'][0]['problemtext'];
		$tempname = $_FILES['data']['tmp_name'][0]['problemtext'];
		if ( strrpos($origname,'.')!==FALSE ) {
			$ext = substr($origname,strrpos($origname,'.')+1);
			if ( in_array($ext, array('txt','html','pdf')) ) {
				$data['problemtext_type'] = $ext;
			}
		}
		// These functions only exist in PHP >= 5.3.0.
		if ( !isset($data['problemtext_type']) &&
		     function_exists("finfo_open") ) {
			$finfo = finfo_open(FILEINFO_MIME);

			list($type, $enc) = explode('; ', finfo_file($finfo, $tempname));

			finfo_close($finfo);

			switch ( $type ) {
			case 'application/pdf':
				$data['problemtext_type'] = 'pdf';
				break;
			case 'text/html':
				$data['problemtext_type'] = 'html';
				break;
			case 'text/plain':
				$data['problemtext_type'] = 'txt';
				break;
			}
		}
		if ( !isset($data['problemtext_type']) ) {
			ch_error("Problem statement has unknown file type.");
		}
	}
	if ( !empty($data['problemtext']) &&
	     !isset($data['problemtext_type']) ) {
		ch_error("Problem statement has unknown file type.");
	}

	return $data;
}

function check_judgehost($data, $keydata = null)
{
	$id = (isset($data['hostname']) ? $data['hostname'] : $keydata['hostname']);

	if ( ! preg_match("/^[A-Za-z0-9_\-.]*$/", $id) ) {
		ch_error("Judgehost has invalid hostname.");
	}

	return $data;
}

function check_language($data, $keydata = null)
{
	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] < 0 ) {
		ch_error("Timelimit is not a valid positive factor");
	}
	$id = (isset($data['langid']) ? $data['langid'] : $keydata['langid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Language ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}

	return $data;
}

function check_relative_time($time, $starttime, $field, $removed_intervals = null)
{
	// FIXME: need to incorporate removed intervals
	if ( empty($time) ) return null;
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
			$seconds = $times[2];
			$seconds = $seconds + 60 * ($minutes + 60 * $hours);
			if ($neg) {
				$seconds *= -1;
			}
			// calculate the absolute time, adjusting for removed intervals
			$abstime = $starttime + $seconds;
			if ( is_array($removed_intervals) ) {
				foreach ( $removed_intervals as $intv ) {
					if ( difftime($intv['starttime'],$abstime)<=0 ) {
						$abstime += difftime($intv['endtime'],$intv['starttime']);
					}
				}
			}
			$ret = $abstime;
		} else {
			ch_error($field . " is not correctly formatted, expecting: +/-hh:mm(:ss)");
			$ret = null;
		}
	} else {
		// Time string is absolute, just convert to Unix epoch
		$ret = strtotime($time);
	}

	return $ret;
}

function check_removed_intervals($contest, $intervals)
{
	foreach ( $intervals as $data ) {
		if ( difftime($data['endtime'], $data['starttime']) <= 0 ) {
			ch_error('Interval ends before (or when) it starts');
		}

		if ( difftime($data['starttime'], $contest['starttime']) < 0 ) {
			ch_error("Interval starttime '$data[starttime]' outside of contest");
		}
		if ( difftime($data['endtime'], $contest['endtime']) > 0 ) {
			ch_error("Interval endtime '$data[endtime]' outside of contest");
		}

		foreach( $intervals as $other ) {
			if ( @$data['intervalid']===@$other['intervalid'] ) continue;
			if ( (difftime($data['starttime'], $other['starttime']) >= 0 &&
			      difftime($data['starttime'], $other['endtime']  ) <  0 ) ||
			     (difftime($data['endtime'],   $other['starttime']) >  0 &&
			      difftime($data['endtime'],   $other['endtime']  ) <= 0 ) ) {
				ch_error('Removed intervals ' .
				         (isset($data['intervalid'])  ? $data['intervalid']  : 'new') .
				         ' and ' .
				         (isset($other['intervalid']) ? $other['intervalid'] : 'new') .
				         ' overlap');
			}
		}
	}
}

function check_contest($data, $keydata = null, $removed_intervals = null)
{
	global $DB;

	// Contest removed intervals are required to correctly calculate
	// absolute contest times from relative ones. Use the ones
	// provides as argument or from the database if available.
	if ( !isset($removed_intervals) && isset($keydata['cid']) ) {
		$removed_intervals = $DB->q('TABLE SELECT * FROM removed_interval
		                             WHERE cid = %i', $keydata['cid']);
	}

	// are these dates valid?
	foreach ( array('starttime','endtime','freezetime',
	                'unfreezetime','activatetime') as $f ) {
		if ( $f == 'starttime' ) {
			$data[$f] = strtotime($data[$f.'_string']);
		} else {
			// The true input date/time strings are preserved in the
			// *_string variables, since these may be relative times
			// that need to be kept as is.
			$data[$f] = $data[$f.'_string'];
			$data[$f] = check_relative_time($data[$f], $data['starttime'], $f,
			                                $removed_intervals);
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

	// check removed_intervals with contest times adapted to these,
	// i.e. we check self-consistency, while a new removed_interval
	// could have been specified that initially has its endtime beyond
	// the contest endtime, but _not_ after correcting the contest
	// endtime for it.
	if ( isset($keydata['cid']) ) check_removed_intervals($data,$removed_intervals);

	// a check whether this contest overlaps in time with any other, the
	// system can only deal with exactly ONE current contest at any time.
	// A new contest N overlaps with an existing contest E if the activate- or
	// end time or N is inside E (N is (partially) contained in E), or if
	// the activatetime is before E and the end time after E (E is completely
	// contained in N).
	if ( $data['enabled'] ) {
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

function check_submission($data, $keydata = null)
{
	return $data;
}

function check_judging($data, $keydata = null)
{
	if ( !empty($data['endtime']) && difftime($data['endtime'], $data['starttime']) < 0 ) {
		ch_error('Judging ended before it started');
	}
	if ( !empty($data['submittime']) && difftime($data['starttime'], $data['submittime']) < 0) {
		ch_error('Judging started before it was submitted (clocks unsynched?)');
	}

	return $data;
}

