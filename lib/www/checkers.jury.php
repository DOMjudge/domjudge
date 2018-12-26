<?php declare(strict_types=1);
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
function ch_error(string $string)
{
    global $CHECKER_ERRORS;
    $CHECKER_ERRORS[] = $string;
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
// If an array $removed_intervals is given, these are use to adjust
// the calculated timestamps for relative times.
function check_relative_time($time, $basetime, $field, $removed_intervals = null)
{
    global $pattern_datetime, $pattern_offset, $human_abs_datetime, $human_rel_datetime;

    if (empty($time)) {
        return null;
    }
    if ($time[0] == '+' || $time[0] == '-') {
        // First check that this is allowed to be a relative time.
        if ($basetime===null) {
            ch_error($field . ' must be specified as absolute time');
            return null;
        }
        // Time string seems relative, check correctness.
        if (preg_match("/^(\-|\+)$pattern_offset\$/", $time)!==1) {
            ch_error($field . " is not correctly formatted, expecting: $human_rel_datetime");
            return null;
        }
        // convert relative times to absolute ones
        $neg = ($time[0] == '-');
        $time[0] = '0';
        $times = explode(':', $time, 3);
        if (count($times) == 2) {
            $times[2] = '00';
        }
        if (count($times) == 3 &&
            is_numeric($times[0]) &&
            is_numeric($times[1]) && $times[1] < 60 &&
            is_numeric($times[2]) && $times[2] < 60) {
            $hours = $times[0];
            $minutes = $times[1];
            $seconds = $times[2];
            $seconds = $seconds + 60 * ($minutes + 60 * $hours);
            if ($neg) {
                $seconds *= -1;
            }
            // calculate the absolute time, adjusting for removed intervals
            $abstime = $basetime + $seconds;
            if (is_array($removed_intervals)) {
                foreach ($removed_intervals as $intv) {
                    if (difftime((float)$intv['starttime'], (float)$abstime)<=0) {
                        $abstime += difftime((float)$intv['endtime'], (float)$intv['starttime']);
                    }
                }
            }
            $ret = $abstime;
        } else {
            ch_error($field . " is not correctly formatted, expecting: $human_rel_datetime");
            return null;
        }
    } else {
        // Time string is absolute, just convert to Unix epoch, but
        // first detect and strip subseconds and timezone, since
        // strtotime doesn't handle these.
        if (preg_match("/^".$pattern_datetime.'$/', $time)!==1) {
            ch_error($field . " is not correctly formatted, expecting: $human_abs_datetime");
            return null;
        }
        // Detect and strip timezone and subseconds.
        $orig_timezone = date_default_timezone_get();
        $timezone = explode(' ', $time)[2];
        $time = substr($time, 0, -(strlen($timezone)+1));
        if (date_default_timezone_set($timezone)!==true) {
            error($field . " contains invalid time zone '$timezone'");
            date_default_timezone_set($orig_timezone);
            return null;
        }
        $subsec = 0;
        if (preg_match('/\.[0-9]{1,6}$/', $time, $match)===1) {
            $subsec = floatval('0'.$match[0]);
            $time = explode('.', $time)[0];
        }
        $ret = floatval(strtotime($time)) + $subsec;
        date_default_timezone_set($orig_timezone);
    }

    return $ret;
}

function check_removed_intervals($contest, $intervals)
{
    foreach ($intervals as $data) {
        foreach (array('starttime','endtime') as $f) {
            // The true input date/time strings are preserved in the
            // *_string variables. These are in absolute format only.
            $data[$f] = $data[$f.'_string'];
            $data[$f] = check_relative_time(
                $data[$f],
                $contest['starttime'],
                'removed_interval '.$f,
                $intervals
            );
        }
    }

    foreach ($intervals as $data) {
        if (difftime((float)$data['endtime'], (float)$data['starttime']) <= 0) {
            ch_error('Interval ends before (or when) it starts');
        }

        if (difftime((float)$data['starttime'], (float)$contest['starttime']) < 0) {
            ch_error("Interval starttime '$data[starttime_string]' outside of contest");
        }
        if (difftime((float)$data['endtime'], (float)$contest['endtime']) > 0) {
            ch_error("Interval endtime '$data[endtime_string]' outside of contest");
        }

        foreach ($intervals as $other) {
            if (@$data['intervalid']===@$other['intervalid']) {
                continue;
            }
            if ((difftime((float)$data['starttime'], (float)$other['starttime']) >= 0 &&
                 difftime((float)$data['starttime'], (float)$other['endtime']) <  0) ||
                (difftime((float)$data['endtime'], (float)$other['starttime']) >  0 &&
                 difftime((float)$data['endtime'], (float)$other['endtime']) <= 0)) {
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
    if (ALLOW_REMOVED_INTERVALS &&
        !isset($removed_intervals) && isset($keydata['cid'])) {
        $removed_intervals = $DB->q('TABLE SELECT * FROM removed_interval
                                     WHERE cid = %i ORDER BY starttime', $keydata['cid']);
    }

    if (isset($data['shortname']) && ! preg_match(ID_REGEX, $data['shortname'])) {
        ch_error("Contest shortname may only contain characters " . IDENTIFIER_CHARS . ".");
    }

    // are these dates valid?
    foreach (array('starttime','endtime','freezetime',
            'unfreezetime','activatetime','deactivatetime') as $f) {
        // The true input date/time strings are preserved in the
        // *_string variables, since these may be relative times
        // that need to be kept as is.
        $data[$f] = @$data[$f.'_string'];
        $data[$f] = check_relative_time(
            $data[$f],
            ($f=='starttime' ? null : $data['starttime']),
            $f,
            $removed_intervals
        );
    }

    // are required times specified?
    foreach (array('activatetime','starttime','endtime') as $f) {
        if (empty($data[$f])) {
            ch_error("Contest $f is empty");
            return $data;
        }
    }

    // the ordering of times is:
    // activatetime <= starttime <= freezetime < endtime <= unfreezetime <= deactivatetime

    // are contest start/end times in order?
    if (difftime($data['endtime'], $data['starttime']) <= 0) {
        ch_error('Contest ends before it even starts');
    }
    if (!empty($data['freezetime'])) {
        if (difftime($data['freezetime'], $data['endtime']) > 0 ||
            difftime($data['freezetime'], $data['starttime']) < 0) {
            ch_error('Freezetime is out of start/endtime range');
        }
    }
    if (difftime($data['activatetime'], $data['starttime']) > 0) {
        ch_error('Activate time is later than starttime');
    }
    if (!empty($data['unfreezetime'])) {
        if (empty($data['freezetime'])) {
            ch_error('Unfreezetime set but no freeze time. That makes no sense.');
        }
        if (difftime($data['unfreezetime'], $data['endtime']) < 0) {
            ch_error('Unfreezetime must be larger than endtime.');
        }
        if (!empty($data['deactivatetime']) &&
            difftime($data['deactivatetime'], $data['unfreezetime']) < 0) {
            ch_error('Deactivatetime must be larger than unfreezetime.');
        }
    } else {
        if (!empty($data['deactivatetime']) &&
            difftime($data['deactivatetime'], $data['endtime']) < 0) {
            ch_error('Deactivatetime must be larger than endtime.');
        }
    }

    // Check removed_intervals with contest times adapted to these,
    // i.e. we check self-consistency, while a new removed_interval
    // could have been specified that initially has its endtime beyond
    // the contest endtime, but _not_ after correcting the contest
    // endtime for it.
    if (ALLOW_REMOVED_INTERVALS && isset($keydata['cid'])) {
        check_removed_intervals($data, $removed_intervals);
    }

    return $data;
}

function check_contestproblem($data, $keydata = null)
{
    if (!is_numeric($data['points']) || $data['points'] < 0) {
        ch_error("Points must be a positive integer.");
    }

    if (isset($data['lazy_eval_results']) &&
        ($data['lazy_eval_results'] < 0 || $data['lazy_eval_results'] > 1)) {
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
    if (!empty($data['endtime']) && difftime($data['endtime'], $data['starttime']) < 0) {
        ch_error('Judging ended before it started');
    }
    if (!empty($data['submittime']) && difftime($data['starttime'], $data['submittime']) < 0) {
        ch_error('Judging started before it was submitted (clocks unsynched?)');
    }

    return $data;
}
