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

function check_user($data, $keydata = null)
{
	global $DB;
	$id = (isset($data['username']) ? $data['username'] : $keydata['username']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Username may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	if ( ! empty($data['email'])  && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
		ch_error("Email not valid.");
	}
	if ( !empty($data['password']) ) {
		$data['password'] = md5("$id#".$data['password']);
	} else {
		unset($data['password']);
	}
	if ( !empty($data['ip_address']) ) {
		if ( !filter_var($data['ip_address'], FILTER_VALIDATE_IP) ) {
			ch_error("Invalid IP address.");
		}
		$ip = $DB->q("VALUE SELECT count(*) FROM user WHERE ip_address = %s AND username != %s", $data['ip_address'], $id);
		if ( $ip > 0 ) {
			ch_error("IP address already assigned to another user.");
		}
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
	global $DB;

	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] < 0 ||
			(int)$data['timelimit'] != $data['timelimit'] ) {
		ch_error("Timelimit is not a valid positive integer");
	}
	if ( isset($data['shortname']) && ! preg_match ( ID_REGEX, $data['shortname'] ) ) {
		ch_error("Problem shortname may only contain characters " . IDENTIFIER_CHARS . ".");
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
		if ( !isset($data['problemtext_type']) ) {
			$finfo = finfo_open(FILEINFO_MIME);

			list($type) = explode('; ', finfo_file($finfo, $tempname));

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
	// Unset problemtext_type if problemtext was set to null explicitly.
	if ( array_key_exists('problemtext', $data) && empty($data['problemtext']) ) {
		$data['problemtext_type'] = NULL;
	}

	if ( !empty($data['special_compare']) ) {
		if ( ! $DB->q('MAYBEVALUE SELECT execid FROM executable
		               WHERE execid = %s AND type = %s',
		              $data['special_compare'], 'compare') ) {
			ch_error("Unknown special compare script (or wrong type): " .
			         $data['special_compare']);
		}
	}
	if ( !empty($data['special_run']) ) {
		if ( ! $DB->q('MAYBEVALUE SELECT execid FROM executable
		               WHERE execid = %s AND type = %s',
		              $data['special_run'], 'run') ) {
			ch_error("Unknown special run script (or wrong type): " .
			         $data['special_run']);
		}
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
	if ( empty($data['compile_script']) ) {
		ch_error("No compile script specified for language: " . $id);
	} else {
		global $DB;
		if ( ! $DB->q('MAYBEVALUE SELECT execid FROM executable
		               WHERE execid = %s AND type = %s',
		              $data['compile_script'], 'compile') ) {
			ch_error("Unknown compile script (or wrong type): " .
			         $data['compile_script']);
		}
	}

	return $data;
}

function check_executable($data, $keydata = null)
{
	$id = (isset($data['execid']) ? $data['execid'] : $keydata['execid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Executable ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	if ( !isset($data['type']) || !in_array($data['type'], $executable_types) ) {
		ch_error("Executable type '" . $data['type'] . "' is invalid.");
	}

	return $data;
}

function check_relative_time($time, $starttime, $field)
{
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
			$ret = $starttime + $seconds;
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

function check_contest($data, $keydata = null)
{
	if ( isset($data['shortname']) && ! preg_match ( ID_REGEX, $data['shortname'] ) ) {
		ch_error("Contest shortname may only contain characters " . IDENTIFIER_CHARS . ".");
	}

	// are these dates valid?
	foreach ( array('starttime','endtime','freezetime',
			'unfreezetime','activatetime','deactivatetime') as $f ) {
		if ( $f == 'starttime' ) {
			$data[$f] = strtotime($data[$f.'_string']);
			if ( $data[$f] === FALSE ) {
				error("Cannot parse starttime: " . $data[$f.'_string']);
			}
		} else {
			// The true input date/time strings are preserved in the
			// *_string variables, since these may be relative times
			// that need to be kept as is.
			$data[$f] = $data[$f.'_string'];
			$data[$f] = check_relative_time($data[$f], $data['starttime'], $f);
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
	// activatetime <= starttime <= freezetime < endtime <= unfreezetime <= deactivatetime

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
		if ( difftime($data['deactivatetime'], $data['unfreezetime']) < 0 ) {
			ch_error('Deactivatetime must be larger than unfreezetime.');
		}
	} else {
		if ( !empty($data['deactivatetime']) && difftime($data['deactivatetime'], $data['endtime']) < 0 ) {
			ch_error('Deactivatetime must be larger than endtime.');
		}
	}

	return $data;
}

function check_contestproblem($data, $keydata = null)
{
	if ( !is_numeric($data['points']) || $data['points'] < 0 ) {
		ch_error("Points must be a positive integer.");
	}

	if ( isset($data['lazy_eval_results'] ) &&
	    ($data['lazy_eval_results'] < 0 || $data['lazy_eval_results'] > 1) ) {
		ch_error("Lazy_eval_results must be empty , 0 or 1.");
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

