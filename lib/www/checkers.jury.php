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

function check_mapping_team($data, $mapping_data, $keydata = null)
{
	// Only when user information is providede (i.e. on add)
	if ( isset($mapping_data[1]) ) {
		if ( $data['adduser'] === '1' ) {
			$id = $mapping_data[1]['extra']['username'];
			if ( ! preg_match ( ID_REGEX, $id ) ) {
				ch_error("Username may only contain characters " . IDENTIFIER_CHARS . ".");
			}

			// Set user fullname to team name
			$mapping_data[1]['extra']['name'] = $data['name'];
		} else {
			// Remove user information when not adding a user
			unset($mapping_data[1]);
		}
	}

	return $mapping_data;
}

function check_team($data, $keydata = null)
{
	// Unset adduser checkbox as it is only a helper checkbox
	if ( isset($data['adduser']) ) {
		unset($data['adduser']);
	}

	return $data;
}

function post_team($prikey, $cmd)
{
	if ( $cmd == 'add' ) {
		global $DB;
		// Add team user-role to user for this team
		$DB->q("INSERT INTO userrole (userid, roleid)
		        SELECT userid, roleid FROM user
		        LEFT JOIN team USING (teamid)
		        LEFT JOIN role ON role.role = 'team'
		        WHERE teamid = %i", $prikey['teamid']);
	}
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
		$data['password'] = dj_password_hash($data['password']);
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

	if ( ! is_numeric($data['timelimit']) || $data['timelimit'] <= 0 ) {
		ch_error("Timelimit is not a valid positive number");
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
	if ( ! is_numeric($data['time_factor']) || $data['time_factor'] <= 0 ) {
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
	$exts = json_decode($data['extensions'], false, 2);
	if ( $exts==null || !is_array($exts) || count($exts)==0 ) {
		ch_error("Language extension list is not a valid non-empty JSON array");
	}

	return $data;
}

function check_executable($data, $keydata = null)
{
	global $executable_types;

	$id = (isset($data['execid']) ? $data['execid'] : $keydata['execid']);
	if ( ! preg_match ( ID_REGEX, $id ) ) {
		ch_error("Executable ID may only contain characters " . IDENTIFIER_CHARS . ".");
	}
	if ( !isset($data['type']) || !in_array($data['type'], $executable_types) ) {
		ch_error("Executable type '" . $data['type'] . "' is invalid.");
	}

	return $data;
}

// Regex patterns for absolute/relative contest time formats. These
// are also used in www/jury/contest.php.
$pattern_timezone  = "[A-Za-z][A-Za-z0-9_\/+-]{1,35}";
$pattern_datetime  = "\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d(\.\d{1,6})? $pattern_timezone";
$pattern_offset    = "\d{1,4}:\d\d(:\d\d(\.\d{1,6})?)?";
$pattern_dateorneg = "($pattern_datetime|-$pattern_offset)";
$pattern_dateorpos = "($pattern_datetime|\+$pattern_offset)";
// Human readable versions of the patterns:
$human_abs_datetime = "YYYY-MM-DD HH:MM:SS[.uuuuuu] timezone";
$human_rel_datetime = "±[HHH]H:MM[:SS[.uuuuuu]]";

// Returns an absolute Unix Epoch timestamp from a formatted absolute
// or relative (to $basetime timestamp, if set) time. $field is a
// descriptive name of the current time for error messages.
function check_relative_time($time, $basetime, $field)
{
	global $pattern_datetime, $pattern_offset, $human_abs_datetime, $human_rel_datetime;

	if ( empty($time) ) return null;
	if ($time[0] == '+' || $time[0] == '-') {
		// First check that this is allowed to be a relative time.
		if ( $basetime===null ) {
			ch_error($field . ' must be specified as absolute time');
			return null;
		}
		// Time string seems relative, check correctness.
		if ( preg_match("/^(\-|\+)$pattern_offset\$/", $time)!==1 ) {
			ch_error($field . " is not correctly formatted, expecting: $human_rel_datetime");
			return null;
		}
		// convert relative times to absolute ones
		$neg = ($time[0] == '-');
		$time[0] = '0';
		$times = explode(':', $time, 3);
		if ( count($times) == 2 ) $times[2] = '00';
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
			$ret = $basetime + $seconds;
		} else {
			ch_error($field . " is not correctly formatted, expecting: $human_rel_datetime");
			return null;
		}
	} else {
		// Time string is absolute, just convert to Unix epoch, but
		// first detect and strip subseconds and timezone, since
		// strtotime doesn't handle these.
		if ( preg_match("/^".$pattern_datetime.'$/', $time)!==1 ) {
			ch_error($field . " is not correctly formatted, expecting: $human_abs_datetime");
			return null;
		}
		// Detect and strip timezone and subseconds.
		$orig_timezone = date_default_timezone_get();
		$timezone = explode(' ', $time)[2];
		$time = substr($time,0,-(strlen($timezone)+1));
		if ( date_default_timezone_set($timezone)!==true ) {
			error($field . " contains invalid time zone '$timezone'");
			date_default_timezone_set($orig_timezone);
			return null;
		}
		$subsec = 0;
		if ( preg_match('/\.[0-9]{1,6}$/', $time, $match)===1 ) {
			$subsec = floatval('0'.$match[0]);
			$time = explode('.', $time)[0];
		}
		$ret = floatval(strtotime($time)) + $subsec;
		date_default_timezone_set($orig_timezone);
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
		// The true input date/time strings are preserved in the
		// *_string variables, since these may be relative times
		// that need to be kept as is.
		$data[$f] = $data[$f.'_string'];
		$data[$f] = check_relative_time($data[$f],
		                                ($f=='starttime' ? null : $data['starttime']), $f);
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
		if ( !empty($data['deactivatetime']) &&
		     difftime($data['deactivatetime'], $data['unfreezetime']) < 0 ) {
			ch_error('Deactivatetime must be larger than unfreezetime.');
		}
	} else {
		if ( !empty($data['deactivatetime']) &&
		     difftime($data['deactivatetime'], $data['endtime']) < 0 ) {
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

