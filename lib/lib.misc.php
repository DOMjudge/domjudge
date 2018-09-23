<?php
/**
 * Miscellaneous helper functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/** Perl regex class of allowed characters in identifier strings. */
define('IDENTIFIER_CHARS', '[a-zA-Z0-9_-]');

/** Perl regex of allowed filenames. */
define('FILENAME_REGEX', '/^[a-zA-Z0-9][a-zA-Z0-9+_\.-]*$/');

require_once('lib.wrappers.php');

/**
 * Will return all the contests that are currently active
 * When fulldata is true, returns the total row as an array
 * instead of just the ID (array indices will be contest ID's then).
 * If $onlyofteam is not null, only show contests that team is part
 * of. If it is -1, only show publicly visible contests
 * If $alsofuture is true, also show the contests that start in the future
 * The results will have the value of field $key in the database as key
 */
function getCurContests(
    $fulldata = false,
    $onlyofteam = null,
                        $alsofuture = false,
    $key = 'cid'
) {
    global $DB;
    if ($alsofuture) {
        $extra = '';
    } else {
        $extra = 'AND activatetime <= UNIX_TIMESTAMP()';
    }
    if ($onlyofteam !== null && $onlyofteam > 0) {
        $contests = $DB->q("SELECT * FROM contest
                            LEFT JOIN contestteam USING (cid)
                            WHERE (contestteam.teamid = %i OR contest.public = 1)
                            AND enabled = 1 ${extra}
                            AND ( deactivatetime IS NULL OR
                                  deactivatetime > UNIX_TIMESTAMP() )
                            ORDER BY activatetime", $onlyofteam);
    } elseif ($onlyofteam === -1) {
        $contests = $DB->q("SELECT * FROM contest
                            WHERE enabled = 1 AND public = 1 ${extra}
                            AND ( deactivatetime IS NULL OR
                                  deactivatetime > UNIX_TIMESTAMP() )
                            ORDER BY activatetime");
    } else {
        $contests = $DB->q("SELECT * FROM contest
                            WHERE enabled = 1 ${extra}
                            AND ( deactivatetime IS NULL OR
                                  deactivatetime > UNIX_TIMESTAMP() )
                            ORDER BY activatetime");
    }
    $contests = $contests->getkeytable($key);
    if (!$fulldata) {
        return array_keys($contests);
    }

    if (ALLOW_REMOVED_INTERVALS) {
        foreach ($contests as $cid => &$contest) {
            $res = $DB->q('KEYTABLE SELECT *, intervalid AS ARRAYKEY
                           FROM removed_interval WHERE cid = %i', $cid);

            $contest['removed_intervals'] = $res;
        }
    }

    return $contests;
}

/**
 * Returns data for a single contest.
 */
function getContest($cid)
{
    global $DB;
    $contest = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $cid);

    if (ALLOW_REMOVED_INTERVALS) {
        $res = $DB->q('KEYTABLE SELECT *, intervalid AS ARRAYKEY
                       FROM removed_interval WHERE cid = %i', $cid);

        $contest['removed_intervals'] = $res;
    }

    return $contest;
}

/**
 * Parse 'id' from HTTP GET or POST variables and check that it is a
 * valid number, or string consisting of IDENTIFIER_CHARS.
 *
 * Returns id as int or string, or NULL if none found.
 */
function getRequestID($numeric = true)
{
    if (empty($_REQUEST['id'])) {
        return null;
    }

    $id = $_REQUEST['id'];
    if ($numeric) {
        if (!preg_match('/^[0-9]+$/', $id)) {
            error("Identifier specified is not a number");
        }
        return (int)$id;
    } else {
        if (!preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id)) {
            error("Identifier specified contains invalid characters");
        }
        return $id;
    }

    // This should never happen:
    error("Could not parse identifier");
}

/**
 * Returns whether the problem with probid is visible to teams and the
 * public. That is, it is in the selected (active) contest, which has
 * started and it is submittable.
 */
function problemVisible($probid)
{
    global $DB, $cdata;

    if (empty($probid)) {
        return false;
    }

    $fdata = calcFreezeData($cdata);
    if (!$fdata['started']) {
        return false;
    }

    return $DB->q('MAYBETUPLE SELECT probid FROM problem
                   INNER JOIN contestproblem USING (probid)
                   WHERE cid = %i AND allow_submit = 1 AND probid = %i',
                  $cdata['cid'], $probid) !== null;
}

/**
 * Given an array of contest data, calculates whether the contest
 * has already started, stopped, andd if scoreboard is currently
 * frozen or final (unfrozen).
 */
function calcFreezeData($cdata, $isjury = false)
{
    $fdata = array();

    if (empty($cdata) || !$cdata['starttime_enabled']) {
        return array(
            'showfinal' => false,
            'showfrozen' => false,
            'started' => false,
            'stopped' => false,
            'running' => false,
        );
    }

    // Show final scores if contest is over and unfreezetime has been
    // reached, or if contest is over and no freezetime had been set.
    // We can compare $now and the dbfields stringwise.
    $now = now();
    $fdata['showfinal']  = isset($cdata['finalizetime']) &&
            difftime($cdata['finalizetime'], $now) <= 0;
    if (!$isjury) {
        $fdata['showfinal'] = $fdata['showfinal'] &&
            (!isset($cdata['freezetime']) ||
             (isset($cdata['unfreezetime']) &&
              difftime($cdata['unfreezetime'], $now) <= 0));
    }
    // freeze scoreboard if freeze time has been reached and
    // we're not showing the final score yet
    $fdata['showfrozen'] = !$fdata['showfinal'] && isset($cdata['freezetime']) &&
                  difftime($cdata['freezetime'], $now) <= 0;
    // contest is active but has not yet started
    $fdata['started'] = difftime($cdata['starttime'], $now) <= 0;
    $fdata['stopped'] = difftime($cdata['endtime'], $now) <= 0;
    $fdata['running'] = ($fdata['started'] && !$fdata['stopped']);

    return $fdata;
}

/**
 * Calculate contest time from wall-clock time (and removed intervals).
 * Returns time since contest start in seconds.
 * NOTE: It is assumed that removed intervals do not overlap and that
 * they all fall within the contest start and end times.
 */
function calcContestTime($walltime, $cid)
{
    $cdata = getContest($cid);

    $contesttime = difftime($walltime, $cdata['starttime']);

    if (ALLOW_REMOVED_INTERVALS) {
        foreach ($cdata['removed_intervals'] as $intv) {
            if (difftime($intv['starttime'], $walltime)<0) {
                $contesttime -= min(
                    difftime($walltime, $intv['starttime']),
                    difftime($intv['endtime'], $intv['starttime'])
                );
            }
        }
    }

    return $contesttime;
}

/**
 * Scoreboard calculation
 *
 * Given a contestid, teamid and a problemid,
 * (re)calculate the values for one row in the scoreboard.
 *
 * Due to current transactions usage, this function MUST NOT contain
 * any START TRANSACTION or COMMIT statements.
 */
function calcScoreRow($cid, $team, $prob)
{
    global $DB;

    logmsg(LOG_DEBUG, "calcScoreRow '$cid' '$team' '$prob'");

    // First acquire an advisory lock to prevent other calls to
    // calcScoreRow() from interfering with our update.
    $lockstr = "domjudge.$cid.$team.$prob";
    if ($DB->q("VALUE SELECT GET_LOCK('$lockstr',3)") != 1) {
        error("calcScoreRow failed to obtain lock '$lockstr'");
    }

    // Note the clause 'submittime < c.endtime': this is used to
    // filter out TOO-LATE submissions from pending, but it also means
    // that these will not count as solved. Correct submissions with
    // submittime after contest end should never happen, unless one
    // resets the contest time after successful judging.
    $result = $DB->q('SELECT result, verified, submittime,
                      (c.freezetime IS NOT NULL && submittime >= c.freezetime) AS afterfreeze
                      FROM submission s
                      LEFT JOIN judging j ON(s.submitid=j.submitid AND j.valid=1)
                      LEFT OUTER JOIN contest c ON(c.cid=s.cid)
                      WHERE teamid = %i AND probid = %i AND s.cid = %i AND s.valid = 1 ' .
                     (dbconfig_get('compile_penalty', 1) ? "" :
                       "AND (j.result IS NULL OR j.result != 'compiler-error') ") .
                     'AND submittime < c.endtime
                      ORDER BY submittime',
                     $team, $prob, $cid);

    // reset vars
    $submitted_j = $pending_j = $time_j = $correct_j = 0;
    $submitted_p = $pending_p = $time_p = $correct_p = 0;

    // for each submission
    while ($row = $result->next()) {

        // Contest submit time
        $submittime = calcContestTime($row['submittime'], $cid);

        // Check if this submission has a publicly visible judging result:
        if ((dbconfig_get('verification_required', 0) && ! $row['verified']) ||
             empty($row['result'])) {
            $pending_j++;
            $pending_p++;
            // Don't do any more counting for this submission.
            continue;
        }

        $submitted_j++;
        if ($row['afterfreeze']) {
            // Show submissions after freeze as pending to the public
            // (if SHOW_PENDING is enabled):
            $pending_p++;
        } else {
            $submitted_p++;
        }

        // if correct, don't look at any more submissions after this one
        if ($row['result'] == 'correct') {
            $correct_j = 1;
            $time_j = $submittime;
            if (! $row['afterfreeze']) {
                $correct_p = 1;
                $time_p = $submittime;
            }
            // stop counting after a first correct submission
            break;
        }
    }

    // insert or update the values in the public/team scores table
    $DB->q('REPLACE INTO scorecache
            (cid, teamid, probid,
             submissions_restricted, pending_restricted, solvetime_restricted, is_correct_restricted,
             submissions_public, pending_public, solvetime_public, is_correct_public)
            VALUES (%i,%i,%i,%i,%i,%i,%i,%i,%i,%i,%i)',
           $cid, $team, $prob,
           $submitted_j, $pending_j, $time_j, $correct_j,
           $submitted_p, $pending_p, $time_p, $correct_p);

    if ($DB->q("VALUE SELECT RELEASE_LOCK('$lockstr')") != 1) {
        error("calcScoreRow failed to release lock '$lockstr'");
    }

    // If we found a new correct result, update the rank cache too
    if ($correct_j > 0 || $correct_p > 0) {
        updateRankCache($cid, $team);
    }

    return;
}

/**
 * Update tables used for efficiently computing team ranks
 *
 * Given a contestid and teamid (re)calculate the time
 * and solved problems for a team.
 *
 * Due to current transactions usage, this function MUST NOT contain
 * any START TRANSACTION or COMMIT statements.
 */
function updateRankCache($cid, $team)
{
    global $DB;

    logmsg(LOG_DEBUG, "updateRankCache '$cid' '$team'");

    $team_penalty = $DB->q("VALUE SELECT penalty FROM team WHERE teamid = %i", $team);

    // First acquire an advisory lock to prevent other calls to
    // calcScoreRow() from interfering with our update.
    $lockstr = "domjudge.$cid.$team";
    if ($DB->q("VALUE SELECT GET_LOCK('$lockstr',3)") != 1) {
        error("updateRankCache failed to obtain lock '$lockstr'");
    }

    // Fetch values from scoreboard cache per problem
    $scoredata = $DB->q("SELECT *, cp.points
                         FROM scorecache
                         LEFT JOIN contestproblem cp USING(probid,cid)
                         WHERE cid = %i and teamid = %i", $cid, $team);

    $num_points = array('public' => 0, 'restricted' => 0);
    $total_time = array('public' => $team_penalty, 'restricted' => $team_penalty);
    while ($srow = $scoredata->next()) {
        // Only count solved problems
        foreach (array('public', 'restricted') as $variant) {
            if ($srow['is_correct_'.$variant]) {
                $penalty = calcPenaltyTime(
                    $srow['is_correct_'.$variant],
                                $srow['submissions_'.$variant]
                );
                $num_points[$variant] += $srow['points'];
                $total_time[$variant] += scoretime($srow['solvetime_'.$variant]) + $penalty;
            }
        }
    }

    // Update the rank cache table
    $DB->q("REPLACE INTO rankcache (cid, teamid,
            points_restricted, totaltime_restricted,
            points_public, totaltime_public)
            VALUES (%i,%i,%i,%i,%i,%i)",
           $cid, $team,
           $num_points['restricted'], $total_time['restricted'],
           $num_points['public'],     $total_time['public']);

    // Release the lock
    if ($DB->q("VALUE SELECT RELEASE_LOCK('$lockstr')") != 1) {
        error("updateRankCache failed to release lock '$lockstr'");
    }
}

/**
 * Update the balloons table after a correct submission.
 *
 * This function double checks that the judging is correct and
 * confirmed.
 */
function updateBalloons($submitid)
{
    global $DB;

    $subm = $DB->q('TUPLE SELECT s.submitid, s.cid, s.probid, s.teamid, j.result, j.verified
                    FROM submission s
                    LEFT JOIN judging j ON (j.submitid=s.submitid AND j.valid=1)
                    WHERE s.submitid = %i', $submitid);

    if (@$subm['result'] !== 'correct') {
        return;
    }

    if (!$subm['verified'] && dbconfig_get('verification_required', 0)) {
        return;
    }

    // prevent duplicate balloons in case of multiple correct submissions
    $numcorrect = $DB->q('VALUE SELECT count(b.submitid)
                          FROM balloon b
                          LEFT JOIN submission s USING(submitid)
                          WHERE valid = 1 AND probid = %i
                          AND teamid = %i AND cid = %i',
                         $subm['probid'], $subm['teamid'], $subm['cid']);

    if ($numcorrect == 0) {
        $balloons_enabled = (bool)$DB->q('VALUE SELECT process_balloons
                                          FROM contest WHERE cid = %i', $subm['cid']);
        if ($balloons_enabled) {
            $DB->q('INSERT INTO balloon (submitid) VALUES (%i)', $submitid);
        }
    }
}

/**
 * Time as used on the scoreboard (i.e. truncated minutes or seconds,
 * depending on the scoreboard resolution setting).
 */
function scoretime($time)
{
    if (dbconfig_get('score_in_seconds', 0)) {
        $result = (int) floor($time);
    } else {
        $result = (int) floor($time / 60);
    }
    return $result;
}

/**
 * Checks whether the team was the first to solve this problem by
 * comparing times. Note that times are floats so a simple equality
 * test is unreliable. Also, $probtime may be NULL when called through
 * putTeamRow(), in which case we simply return FALSE.
 */
function first_solved($teamtime, $probtime)
{
    if (!isset($probtime)) {
        return false;
    }
    $eps = 0.0000001;
    return $teamtime-$eps <= $probtime;
}

/**
 * Calculate the penalty time.
 *
 * This is here because it is used by the caching functions above.
 *
 * This expects bool $solved (whether there was at least one correct
 * submission by this team for this problem) and int $num_submissions
 * (the total number of tries for this problem by this team)
 * as input, uses the 'penalty_time' variable and outputs the number
 * of penalty minutes.
 *
 * The current formula is as follows:
 * - Penalty time is only counted for problems that the team finally
 *   solved. Yet unsolved problems always have zero penalty minutes.
 * - The penalty is 'penalty_time' (usually 20 minutes) for each
 *   unsuccessful try. By definition, the number of unsuccessful
 *   tries is the number of submissions for a problem minus 1: the
 *   final, correct one.
 */

function calcPenaltyTime($solved, $num_submissions)
{
    if (! $solved) {
        return 0;
    }

    $result = ($num_submissions - 1) * dbconfig_get('penalty_time', 20);
    //  Convert the penalty time to seconds if the configuration
    //  parameter to compute scores to the second is set.
    if (dbconfig_get('score_in_seconds', 0)) {
        $result *= 60;
    }

    return $result;
}

// From http://www.problemarchive.org/wiki/index.php/Problem_Format

// Expected result tag in (jury) submissions:
$problem_result_matchstrings = array('@EXPECTED_RESULTS@: ',
                                     '@EXPECTED_SCORE@: ');

// Remap from Kattis problem package format to DOMjudge internal strings:
$problem_result_remap = array('ACCEPTED' => 'CORRECT',
                              'WRONG_ANSWER' => 'WRONG-ANSWER',
                              'TIME_LIMIT_EXCEEDED' => 'TIMELIMIT',
                              'RUN_TIME_ERROR' => 'RUN-ERROR');

function normalizeExpectedResult($result)
{
    global $problem_result_remap;

    $result = trim(mb_strtoupper($result));
    if (in_array($result, array_keys($problem_result_remap))) {
        return $problem_result_remap[$result];
    }
    return $result;
}

/**
 * checks given source file for expected results string
 * returns NULL if no such string exists
 * returns array of expected results otherwise
 */
function getExpectedResults($source)
{
    global $problem_result_matchstrings;
    $pos = false;
    foreach ($problem_result_matchstrings as $matchstring) {
        if (($pos = mb_stripos($source, $matchstring)) !== false) {
            break;
        }
    }

    if ($pos === false) {
        return null;
    }

    $beginpos = $pos + mb_strlen($matchstring);
    $endpos = mb_strpos($source, "\n", $beginpos);
    $str = mb_substr($source, $beginpos, $endpos-$beginpos);
    $results = explode(',', trim(mb_strtoupper($str)));

    foreach ($results as $key => $val) {
        $results[$key] = normalizeExpectedResult($val);
    }

    return $results;
}

/**
 * Determines final result for a judging given an ordered array of
 * testcase results. Testcase results can have value NULL if not run
 * yet. A return value of NULL means that a final result cannot be
 * determined yet; this may only occur when not all testcases have
 * been run yet.
 */
function getFinalResult($runresults, $results_prio = null)
{
    if (empty($results_prio)) {
        $results_prio  = dbconfig_get('results_prio');
    }

    // Whether we have NULL results
    $havenull = false;

    // This stores the current result and priority to be returned:
    $bestres  = null;
    $bestprio = -1;

    // Find first highest priority result:
    foreach ($runresults as $tc => $res) {
        if ($res===null) {
            $havenull = true;
        } else {
            $prio = $results_prio[$res];
            if (empty($prio)) {
                error("Unknown result '$res' found.");
            }
            if ($prio>$bestprio) {
                $bestres  = $res;
                $bestprio = $prio;
            }
        }
    }

    // If we have NULL results, check whether the highest priority
    // result has maximal priority. Use a local copy of the
    // 'results_prio' array, keeping the original untouched.
    $tmp = $results_prio;
    rsort($tmp);
    $maxprio = reset($tmp);

    // No highest priority result found: no final answer yet.
    if ($havenull && $bestprio<$maxprio) {
        return null;
    }

    return $bestres;
}

/**
 * Calculate timelimit overshoot from actual timelimit and configured
 * overshoot that can be specified as a sum,max,min of absolute and
 * relative times. Returns overshoot seconds as a float.
 */
function overshoot_time($timelimit, $overshoot_cfg)
{
    $tokens = preg_split('/([+&|])/', $overshoot_cfg, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($tokens)!=1 && count($tokens)!=3) {
        error("invalid timelimit overshoot string '$overshoot_cfg'");
    }

    $val1 = overshoot_parse($timelimit, $tokens[0]);
    if (count($tokens)==1) {
        return $val1;
    }

    $val2 = overshoot_parse($timelimit, $tokens[2]);
    switch ($tokens[1]) {
    case '+': return $val1 + $val2;
    case '|': return max($val1, $val2);
    case '&': return min($val1, $val2);
    }
    error("invalid timelimit overshoot string '$overshoot_cfg'");
}

/**
 * Helper function for overshoot_time(), returns overshoot for single token.
 */
function overshoot_parse($timelimit, $token)
{
    $res = sscanf($token, '%d%c%n');
    if (count($res)!=3) {
        error("invalid timelimit overshoot token '$token'");
    }
    list($val, $type, $len) = $res;
    if (strlen($token)!=$len) {
        error("invalid timelimit overshoot token '$token'");
    }

    if ($val<0) {
        error("timelimit overshoot cannot be negative: '$token'");
    }
    switch ($type) {
    case 's': return $val;
    case '%': return $timelimit * 0.01*$val;
    default: error("invalid timelimit overshoot token '$token'");
    }
}

/* The functions below abstract away the precise time format used
 * internally. We currently use Unix epoch with up to 9 decimals for
 * subsecond precision.
 */

/**
 * Simulate MySQL UNIX_TIMESTAMP() function to create insert queries
 * that do not change when replicated later.
 */
function now()
{
    return microtime(true);
}

/**
 * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
 * Returned value is time difference in seconds.
 */
function difftime($time1, $time2)
{
    return $time1 - $time2;
}

/**
 * Call alert plugin program to perform user configurable action on
 * important system events. See default alert script for more details.
 */
function alert($msgtype, $description = '')
{
    system(LIBDIR . "/alert '$msgtype' '$description' &");
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler($signal)
{
    global $exitsignalled, $gracefulexitsignalled;

    logmsg(LOG_DEBUG, "Signal $signal received");

    switch ($signal) {
        case SIGHUP:
            $gracefulexitsignalled = true;
            // no break
        case SIGINT:   # Ctrl+C
        case SIGTERM:
            $exitsignalled = true;
    }
}

function initsignals()
{
    global $exitsignalled;

    $exitsignalled = false;

    if (! function_exists('pcntl_signal')) {
        logmsg(LOG_INFO, "Signal handling not available");
        return;
    }

    logmsg(LOG_DEBUG, "Installing signal handlers");

    // Install signal handler for TERMINATE, HANGUP and INTERRUPT
    // signals. The sleep() call will automatically return on
    // receiving a signal.
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
}

/**
 * Forks and detaches the current process to run as a daemon. Similar
 * to the daemon() call present in Linux and *BSD.
 *
 * Argument pidfile is an optional filename to check for running
 * instances and write PID to.
 *
 * Either returns successfully or exits with an error.
 */
function daemonize($pidfile = null)
{
    switch ($pid = pcntl_fork()) {
        case -1: error("cannot fork daemon");
        case  0: break; // child process: do nothing here.
        default: exit;  // parent process: exit.
    }

    if (($pid = posix_getpid())===false) {
        error("failed to obtain PID");
    }

    // Check and write PID to file
    if (!empty($pidfile)) {
        if (($fd=@fopen($pidfile, 'x+'))===false) {
            error("cannot create pidfile '$pidfile'");
        }
        $str = "$pid\n";
        if (@fwrite($fd, $str)!=strlen($str)) {
            error("failed writing PID to file");
        }
        register_shutdown_function('unlink', $pidfile);
    }

    // Notify user with daemon PID before detaching from TTY.
    logmsg(LOG_NOTICE, "daemonizing with PID = $pid");

    // Close std{in,out,err} file descriptors
    if (!fclose(STDIN) || !($GLOBALS['STDIN']  = fopen('/dev/null', 'r')) ||
        !fclose(STDOUT) || !($GLOBALS['STDOUT'] = fopen('/dev/null', 'w')) ||
        !fclose(STDERR) || !($GLOBALS['STDERR'] = fopen('/dev/null', 'w'))) {
        error("cannot reopen stdio files to /dev/null");
    }

    // FIXME: We should really close all other open file descriptors
    // here, but PHP does not support this.

    // Start own process group, detached from any tty
    if (posix_setsid()<0) {
        error("cannot set daemon process group");
    }
}

/**
 * This function takes a (set of) temporary file(s) of a submission,
 * validates it and puts it into the database. Additionally it
 * moves it to a backup storage.
 */
function submit_solution(
    $team,
    $prob,
    $contest,
    $lang,
    $files,
    $filenames,
    $origsubmitid = null,
    $entry_point = null,
    $extid = null,
    $submittime = null,
    $extresult = null
) {
    global $DB;

    if (empty($team)) {
        error("No value for Team.");
    }
    if (empty($prob)) {
        error("No value for Problem.");
    }
    if (empty($contest)) {
        error("No value for Contest.");
    }
    if (empty($lang)) {
        error("No value for Language.");
    }

    if (empty($submittime)) {
        $submittime = now();
    }

    if (!is_array($files) || count($files)==0) {
        error("No files specified.");
    }
    if (count($files) > dbconfig_get('sourcefiles_limit', 100)) {
        error("Tried to submit more than the allowed number of source files.");
    }
    if (!is_array($filenames) || count($filenames)!=count($files)) {
        error("Nonmatching (number of) filenames specified: " .
              count($filenames) . " vs. " . count($files));
    }

    if (count($filenames)!=count(array_unique($filenames))) {
        error("Duplicate filenames detected.");
    }

    $sourcesize = dbconfig_get('sourcesize_limit');

    // If no contest has started yet, refuse submissions.
    $now = now();

    $contestdata = $DB->q('MAYBETUPLE SELECT * FROM contest WHERE cid = %i', $contest);
    if (! isset($contestdata)) {
        error("Contest c$contest not found.");
    }
    $fdata = calcFreezeData($contestdata);
    if (!checkrole('jury') && !$fdata['started']) {
        error("The contest is closed, no submissions accepted. [c$contest]");
    }

    // Check 2: valid parameters?
    if (! $langdata = $DB->q('MAYBETUPLE SELECT langid, require_entry_point FROM language
                              WHERE langid = %s AND allow_submit = 1', $lang)) {
        error("Language '$lang' not found in database or not submittable.");
    }
    $langid = $langdata['langid'];
    if ($langdata['require_entry_point'] && empty($entry_point)) {
        error("Entry point required for '$langid' but none given.");
    }
    if (checkrole('jury') && $entry_point == '__auto__') {
        // Fall back to auto detection when we're importing jury submissions.
        $entry_point = NULL;
    }
    if (! $teamid = $DB->q('MAYBEVALUE SELECT teamid FROM team
                            WHERE teamid = %i' .
                           (checkrole('jury') ? '' : ' AND enabled = 1'), $team)) {
        error("Team '$team' not found in database or not enabled.");
    }
    $probdata = $DB->q('MAYBETUPLE SELECT probid, points FROM problem
                        INNER JOIN contestproblem USING (probid)
                        WHERE probid = %s AND cid = %i AND allow_submit = 1',
                       $prob, $contest);

    if (empty($probdata)) {
        error("Problem p$prob not found in database or not submittable [c$contest].");
    } else {
        $points = $probdata['points'];
        $probid = $probdata['probid'];
    }

    // Reindex arrays numerically to allow simultaneously iterating
    // over both $files and $filenames.
    $files     = array_values($files);
    $filenames = array_values($filenames);

    $totalsize = 0;
    for ($i=0; $i<count($files); $i++) {
        if (! is_readable($files[$i])) {
            error("File '".$files[$i]."' not found (or not readable).");
        }
        if (! preg_match(FILENAME_REGEX, $filenames[$i])) {
            error("Illegal filename '".$filenames[$i]."'.");
        }
        $totalsize += filesize($files[$i]);
    }
    if ($totalsize > $sourcesize*1024) {
        error("Submission file(s) are larger than $sourcesize kB.");
    }

    logmsg(LOG_INFO, "input verified");

    // First look up any expected results in file, so as to minimize
    // the SQL transaction time below.
    if (checkrole('jury')) {
        $results = getExpectedResults(dj_file_get_contents($files[0]));
    }

    // Insert submission into the database
    $DB->q('START TRANSACTION');
    $id = $DB->q('RETURNID INSERT INTO submission
                  (cid, teamid, probid, langid, submittime, origsubmitid, entry_point,
                   externalid, externalresult)
                  VALUES (%i, %i, %i, %s, %s, %i, %s, %s, %s)',
                 $contest, $teamid, $probid, $langid, $submittime,
                 $origsubmitid, $entry_point, $extid, $extresult);

    for ($rank=0; $rank<count($files); $rank++) {
        $DB->q('INSERT INTO submission_file
                (submitid, filename, rank, sourcecode) VALUES (%i, %s, %i, %s)',
               $id, $filenames[$rank], $rank, dj_file_get_contents($files[$rank]));
    }

    // Add expected results from source. We only do this for jury
    // submissions to prevent accidental auto-verification of team
    // submissions.
    if (checkrole('jury') && !empty($results)) {
        $DB->q('UPDATE submission SET expected_results=%s
                WHERE submitid=%i', dj_json_encode($results), $id);
    }
    eventlog('submission', $id, 'create', $contest);
    $DB->q('COMMIT');

    // Recalculate scoreboard cache for pending submissions
    calcScoreRow($contest, $teamid, $probid);

    alert('submit', "submission $id: team $teamid, language $langid, problem $probid");

    if (is_writable(SUBMITDIR)) {
        // Copy the submission to SUBMITDIR for safe-keeping
        for ($rank=0; $rank<count($files); $rank++) {
            $fdata = array(
                'cid' => $contest,
                'submitid' => $id,
                'teamid' => $teamid,
                'probid' => $probid,
                'langid' => $langid,
                'rank' => $rank,
                'filename' => $filenames[$rank]
            );
            $tofile = SUBMITDIR . '/' . getSourceFilename($fdata);
            if (! @copy($files[$rank], $tofile)) {
                warning("Could not copy '" . $files[$rank] . "' to '" . $tofile . "'");
            }
        }
    } else {
        logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
    }

    if (difftime($contestdata['endtime'], $submittime) <= 0) {
        logmsg(LOG_INFO, "The contest is closed, submission stored but not processed. [c$contest]");
    }

    return $id;
}

/**
 * Triggers rejudging submissions.
 * Returns the rejudgingid if $full_rejudge, otherwise NULL.
 *
 * $table         Can be one from $tablemap below.
 * $id            Selects submisions that match this ID in the given table.
 * $include_all   Determines if also correct submissions or those with
 *                  pending judgements are included.
 * $full_rejudge  Creates a full-blown rejudging that has to be approved
 *                  instead of rejudging on the fly.
 * $reason        Reason included in a full rejudging.
 * $userid        Who triggered the full rejudging.
 */
function rejudge($table, $id, $include_all, $full_rejudge, $reason = null, $userid = null)
{
    global $DB;

    /* These are the tables that we can deal with. */
    $tablemap = array(
        'contest'    => 's.cid',
        'judgehost'  => 'j.judgehost',
        'language'   => 's.langid',
        'problem'    => 's.probid',
        'submission' => 's.submitid',
        'team'       => 's.teamid'
    );

    if (!isset($tablemap[$table])) {
        error("unknown table in rejudging");
    }

    // This can be done in one Update from MySQL 4.0.4 and up, but
    // that wouldn't allow us to call calcScoreRow() for the right
    // rows, so we'll just loop over the results one at a time.

    // Only rejudge submissions in active contests.
    $cids = getCurContests(false);

    // Do not include pending/queued or ignored submissions in rejudge.
    $restrictions = 'result IS NOT NULL AND s.valid=1 AND ';
    if ($include_all) {
        if (!$full_rejudge) {
            $restrictions = '';
        }
    } else {
        $restrictions .= 'result != \'correct\' AND ';
    }
    $res = $DB->q('SELECT j.judgingid, s.submitid, s.teamid, s.probid, j.cid, s.rejudgingid
                   FROM judging j
                   LEFT JOIN submission s USING (submitid)
                   WHERE j.cid IN (%Ai) AND j.valid = 1 AND ' .
                  $restrictions .
                  $tablemap[$table] . ' = %s', $cids, $id);

    if ($res->count() == 0) {
        error("No judgings matched.");
    }

    if ($full_rejudge) {
        $rejudgingid = $DB->q('RETURNID INSERT INTO rejudging
                               (userid_start, starttime, reason) VALUES (%i, %s, %s)',
                              $userid, now(), $reason);
    }

    while ($jud = $res->next()) {
        if (isset($jud['rejudgingid'])) {
            // already associated rejudging
            if ($table == 'submission') {
                // clean up rejudging
                if ($full_rejudge) {
                    $DB->q('DELETE FROM rejudging WHERE rejudgingid=%i', $rejudgingid);
                }
                error('submission is already part of rejudging r' . specialchars($jud['rejudgingid']));
            } else {
                // silently skip that submission
                continue;
            }
        }

        $DB->q('START TRANSACTION');

        if (!$full_rejudge) {
            $DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i', $jud['judgingid']);
        }

        $DB->q('UPDATE submission SET judgehost = NULL' .
               ($full_rejudge ? ', rejudgingid=%i ' : '%_ ') .
               'WHERE submitid = %i AND rejudgingid IS NULL',
               @$rejudgingid, $jud['submitid']);

        // Prioritize single submission rejudgings
        if ($table == 'submission') {
            $DB->q('UPDATE team SET judging_last_started = NULL
                    WHERE teamid = %i', $jud['teamid']);
        }

        if (!$full_rejudge) {
            calcScoreRow($jud['cid'], $jud['teamid'], $jud['probid']);
        }
        $DB->q('COMMIT');

        if (!$full_rejudge) {
            auditlog('judging', $jud['judgingid'], 'mark invalid', '(rejudge)');
        }
    }

    return $full_rejudge ? $rejudgingid : null;
}

/**
 * Finish a full rejudging by either applying or cancelling its results.
 *
 * $request can be either 'apply' or 'cancel'.
 */
function rejudging_finish($rejudgingid, $request, $userid = null, $show_progress = false)
{
    global $DB;

    if (!in_array($request, array('apply','cancel'), true)) {
        error("Invalid request type");
    }

    $rejdata = $DB->q('TUPLE SELECT * FROM rejudging
                       WHERE rejudgingid=%i', $rejudgingid);

    if (! $rejdata) {
        error("Invalid rejudging ID");
    }

    if (isset($rejdata['endtime'])) {
        error("Rejudging already " . ($rejdata['valid'] ? 'applied.' : 'canceled.'));
    }

    $todo = $DB->q('VALUE SELECT COUNT(*) FROM submission
                    WHERE rejudgingid=%i', $rejudgingid);
    $done = $DB->q('VALUE SELECT COUNT(*) FROM judging
                    WHERE rejudgingid=%i AND endtime IS NOT NULL', $rejudgingid);
    $todo -= $done;

    if ($request=='apply' && $todo > 0) {
        error("$todo unfinished judgings left, cannot apply rejudging.");
    }

    $res = $DB->q('SELECT s.submitid, s.cid, s.teamid, s.probid, j.judgingid, j.result
                   FROM submission s
                   LEFT JOIN judging j USING(submitid)
                   WHERE s.rejudgingid=%i
                   AND j.rejudgingid=%i', $rejudgingid, $rejudgingid);

    auditlog('rejudging', $rejudgingid, $request.'ing rejudge', '(start)');

    while ($row = $res->next()) {
        if ($show_progress) {
            echo "s" . specialchars($row['submitid']) . ", ";
        }
        if ($request=='apply') {
            $DB->q('START TRANSACTION');
            // first invalidate old judging, maybe different from prevjudgingid!
            $DB->q('UPDATE judging SET valid=0
                    WHERE submitid=%i', $row['submitid']);
            // then set judging to valid
            $DB->q('UPDATE judging SET valid=1
                    WHERE submitid=%i AND rejudgingid=%i', $row['submitid'], $rejudgingid);
            // remove relation from submission to rejudge
            $DB->q('UPDATE submission SET rejudgingid=NULL
                    WHERE submitid=%i', $row['submitid']);
            // update event log
            eventlog('judging', $row['judgingid'], 'create', $row['cid']);
            $run_ids = $DB->q('COLUMN SELECT runid FROM judging_run
                               WHERE judgingid=%i', $row['judgingid']);
            if (!empty($run_ids)) {
                eventlog('judging_run', $run_ids, 'create', $row['cid']);
            }
            // last update cache
            calcScoreRow($row['cid'], $row['teamid'], $row['probid']);
            $DB->q('COMMIT');
            updateBalloons($row['submitid']);
        } else {
            // restore old judgehost association
            $valid_judgehost = $DB->q('VALUE SELECT judgehost FROM judging
                                       WHERE submitid=%i AND valid=1', $row['submitid']);
            $DB->q('UPDATE submission SET rejudgingid = NULL, judgehost=%s
                    WHERE rejudgingid = %i', $valid_judgehost, $rejudgingid);
        }
    }

    $DB->q('UPDATE rejudging
            SET endtime=%s, userid_finish=%i, valid=%i
            WHERE rejudgingid=%i',
           now(), $userid, ($request=='apply' ? 1 : 0), $rejudgingid);

    auditlog('rejudging', $rejudgingid, $request.'ing rejudge', '(end)');
}

/**
 * Compute the filename of a given submission. $fdata must be an array
 * that contains the data from submission and submission_file.
 */
function getSourceFilename($fdata)
{
    return implode('.', array('c'.$fdata['cid'], 's'.$fdata['submitid'],
                              't'.$fdata['teamid'], 'p'.$fdata['probid'], $fdata['langid'],
                              $fdata['rank'], $fdata['filename']));
}

/**
 * Output generic version information and exit.
 */
function version()
{
    echo SCRIPT_ID . " -- part of DOMjudge version " . DOMJUDGE_VERSION . "\n" .
        "Written by the DOMjudge developers\n\n" .
        "DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n" .
        "are welcome to redistribute it under certain conditions.  See the GNU\n" .
        "General Public Licence for details.\n";
    exit(0);
}

/**
 * Word wrap only unquoted text.
 */
function wrap_unquoted($text, $width = 75, $quote = '>')
{
    $lines = explode("\n", $text);

    $result = '';
    $unquoted = '';

    foreach ($lines as $line) {
        // Check for quoted lines
        if (strspn($line, $quote)>0) {
            // First append unquoted text wrapped, then quoted line:
            $result .= wordwrap($unquoted, $width);
            $unquoted = '';
            $result .= $line . "\n";
        } else {
            $unquoted .= $line . "\n";
        }
    }

    $result .= wordwrap(rtrim($unquoted), $width);

    return $result;
}

/**
 * Log an action to the auditlog table.
 */
function auditlog(
    $datatype,
    $dataid,
    $action,
    $extrainfo = null,
                  $force_username = null,
    $cid = null
) {
    global $username, $DB;

    if (!empty($force_username)) {
        $user = $force_username;
    } else {
        $user = $username;
    }

    $DB->q('INSERT INTO auditlog
            (logtime, cid, user, datatype, dataid, action, extrainfo)
            VALUES(%s, %i, %s, %s, %s, %s, %s)',
           now(), $cid, $user, $datatype, $dataid, $action, $extrainfo);
}

/* Mapping from REST API endpoints to relevant information:
 * - type: one of 'configuration', 'live', 'aggregate'
 * - url: REST API URL of endpoint relative to baseurl, defaults to '/<endpoint>'
 * - tables: array of database table(s) associated to data, defaults to <endpoint> without 's'
 * - extid: database field for external/API ID, if TRUE same as internal/DB ID.
 *
 */
$API_endpoints = array(
    'contests' => array(
        'type'   => 'configuration',
        'url'    => '',
        'extid'  => true,
    ),
    'judgement-types' => array( // hardcoded in $VERDICTS and the API
        'type'   => 'configuration',
        'tables' => array(),
        'extid'  => true,
    ),
    'languages' => array(
        'type'   => 'configuration',
        'extid'  => 'externalid',
    ),
    'problems' => array(
        'type'   => 'configuration',
        'tables' => array('problem', 'contestproblem'),
        'extid'  => true,
    ),
    'groups' => array(
        'type'   => 'configuration',
        'tables' => array('team_category'),
        'extid'  => true, // FIXME
    ),
    'organizations' => array(
        'type'   => 'configuration',
        'tables' => array('team_affiliation'),
        'extid'  => true,
    ),
    'teams' => array(
        'type'   => 'configuration',
        'tables' => array('team', 'contestteam'),
        'extid'  => true,
    ),
/*
    'team-members' => array(
        'type'   => 'configuration',
        'tables' => array(),
    ),
*/
    'state' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'submissions' => array(
        'type'   => 'live',
        'extid'  => true, // 'externalid,cid' in ICPC-live branch
    ),
    'judgements' => array(
        'type'   => 'live',
        'tables' => array('judging'),
        'extid'  => true,
    ),
    'runs' => array(
        'type'   => 'live',
        'tables' => array('judging_run'),
        'extid'  => true,
    ),
    'clarifications' => array(
        'type'   => 'live',
        'extid'  => true,
    ),
    'awards' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'scoreboard' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'event-feed' => array(
        'type'   => 'aggregate',
        'tables' => array('event'),
    ),
    // From here are DOMjudge extensions:
    'users' => array(
        'type'   => 'configuration',
        'url'    => null,
        'extid'  => true,
    ),
    'testcases' => array(
        'type'   => 'configuration',
        'url'    => null,
        'extid'  => true,
    ),
);
// Add defaults to mapping:
foreach ($API_endpoints as $endpoint => $data) {
    if (!array_key_exists('url', $data)) {
        $API_endpoints[$endpoint]['url'] = '/'.$endpoint;
    }
    if (!array_key_exists('tables', $data)) {
        $API_endpoints[$endpoint]['tables'] = array( preg_replace('/s$/', '', $endpoint) );
    }
}

/**
 * Map an internal/DB ID to an external/REST endpoint ID.
 *
 * TODO: add support for multiple $intid's, so we can use this in the eventlog() function and not have a loop there
 */
function rest_extid($endpoint, $intid)
{
    global $DB, $API_endpoints, $KEYS;

    if ($intid===null) {
        return null;
    }

    $ep = @$API_endpoints[$endpoint];
    if (!isset($ep['extid'])) {
        error("no int/ext ID mapping defined for $endpoint");
    }

    if ($ep['extid']===true) {
        return $intid;
    }

    $extkey = explode(',', $ep['extid'])[0];

    $extid = $DB->q('MAYBEVALUE SELECT `' . $extkey . '`
                     FROM `' . $ep['tables'][0] . '`
                     WHERE `' . $KEYS[$ep['tables'][0]][0] . '` = %s', $intid);

    return $extid;
}

/**
 * Map an external/REST endpoint ID back to an internal/DB ID.
 *
 * TODO: add support for multiple $extid's, so we can use this in the API and not have a loop there
 */
function rest_intid($endpoint, $extid, $cid = null)
{
    global $DB, $API_endpoints, $KEYS;

    if ($extid===null) {
        return null;
    }

    $ep = @$API_endpoints[$endpoint];
    if (!isset($ep['extid'])) {
        error("no int/ext ID mapping defined for $endpoint");
    }

    if ($ep['extid']===true) {
        return $extid;
    }

    if (!$ep['tables']) {
        error("no database table known for $endpoint");
    }

    $keys = explode(',', $ep['extid']);
    $extkey = $keys[0];
    if (count($keys)>1) {
        $cidkey = $keys[1];
    }

    if (isset($cidkey) && $cid===null) {
        error("argument 'cid' missing to map to internal ID for $endpoint");
    }

    $intid = $DB->q('MAYBEVALUE SELECT `' . $KEYS[$ep['tables'][0]][0] . '`
                     FROM `' . $ep['tables'][0] . '`
                     WHERE `' . $extkey . '` = %s' .
                    (isset($cidkey) ? ' AND cid = %i' : ' %_'),
                    $extid, $cid);

    return $intid;
}

/**
 * Log an event.
 *
 * Arguments:
 * $type      Either an API endpoint or a DB table.
 * $dataids   Identifier(s) of the row in the associated DB table
 *            as either one ID or an array of ID's.
 * $action    One of: create, update, delete.
 * $cid       Contest ID to log this event for. If null, log it for
 *            all currently active contests.
 * $json      JSON content after the change. Generated if null.
 * $ids       Identifier(s) as shown in the REST API. If null it is
 *            inferred from the content in the database or $json
 *            passed as argument. Must be specified when deleting an
 *            entry or if no DB table is associated to $type.
 *            Can be null, one ID or an array of ID's.
 */
// TODO: we should probably integrate this function with auditlog().
function eventlog($type, $dataids, $action, $cid = null, $json = null, $ids = null)
{
    global $DB, $API_endpoints;

    if (!is_array($dataids)) {
        $dataids = [$dataids];
    }

    if (count($dataids) > 1 && isset($ids)) {
        logmsg(LOG_WARNING, "eventlog: passing multiple dataid's while also passing one or more ID's not allowed yet");
        return;
    }

    if (count($dataids) > 1 && isset($json)) {
        logmsg(LOG_WARNING, "eventlog: passing multiple dataid's while also passing a JSON object not allowed yet");
        return;
    }

    // Make a combined string to keep track of the data ID's
    // TODO: if some data ID's contain a comma, this breaks
    $dataidsCombined = implode(',', $dataids);
    // TODO: if some ID's contain a comma, this breaks
    $idsCombined = $ids === null ? null : is_array($ids) ? implode(',', $ids) : $ids;

    logmsg(LOG_DEBUG, "eventlog arguments: '$type' '$dataidsCombined' '$action' '$cid' '$json' '$idsCombined'");

    $actions = array('create', 'update', 'delete');

    // Gracefully fail since we may call this from the generic
    // jury/edit.php page where we don't know which table gets updated.
    if (array_key_exists($type, $API_endpoints)) {
        $endpoint = $API_endpoints[$type];
    } else {
        foreach ($API_endpoints as $key => $ep) {
            if (in_array($type, $ep['tables'], true)) {
                $type = $key;
                $endpoint = $ep;
                break;
            }
        }
    }
    if (!isset($endpoint)) {
        logmsg(LOG_WARNING, "eventlog: invalid endpoint '$type' specified");
        return;
    }
    if (!in_array($action, $actions)) {
        logmsg(LOG_WARNING, "eventlog: invalid action '$action' specified");
        return;
    }
    if ($endpoint['url']===null) {
        logmsg(LOG_DEBUG, "eventlog: no endpoint for '$type', ignoring");
        return;
    }

    // Look up external/API ID from various sources.
    if ($ids===null) {
        $ids = array_map(function ($dataid) use ($type) {
            return rest_extid($type, $dataid);
        }, $dataids);
    }

    if ($ids===[null] && $json!==null) {
        $data = dj_json_decode($json);
        if (!empty($data['id'])) {
            $ids = [$data['id']];
        }
    }

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    // State is a special case, as it works without an ID
    if ($type !== 'state' && count(array_filter($ids)) !== count($dataids)) {
        logmsg(LOG_WARNING, "eventlog: API ID not specified or inferred from data");
        return;
    }

    // Make sure ID arrays are 0-indexed
    $dataids = array_values($dataids);
    $ids = array_values($ids);

    $cids = [];
    if ($cid!==null) {
        $cids[] = $cid;
        $expectedEvents = count($dataids);
    } else {
        if ($type==='problems') {
            $expectedEvents = 0;
            foreach ($dataids as $dataid) {
                $cidsForId = $DB->q('COLUMN SELECT DISTINCT cid FROM contestproblem WHERE probid = %i', $dataid);
                $expectedEvents += count($cidsForId);
                $cids = array_unique(array_merge($cids, $cidsForId));
            }
        } elseif ($type==='teams') {
            $expectedEvents = 0;
            foreach ($dataids as $dataid) {
                $cidsForId = getCurContests(false, $dataid);
                $expectedEvents += count($cidsForId);
                $cids = array_unique(array_merge($cids, $cidsForId));
            }
        } else {
            $cids = getCurContests();
            $expectedEvents = count($dataids) * count($cids);
        }
    }
    if (count($cids)==0) {
        logmsg(LOG_INFO, "eventlog: no active contests associated to update.");
        return;
    }

    // TODO: if some ID's contain a comma, this breaks
    $idsCombined = implode(',', $ids);

    // We should pass multiple ID's if instructed to do so
    $multiple = count($dataids) > 1 || count($ids) > 1;

    // Generate JSON content if not set, for deletes this is only the ID.
    if ($action === 'delete') {
        $json = array_values(array_map(function ($id) {
            return ['id' => $id];
        }, $ids));

        // If we do not have multiple ID's, the code assumes the JSON is only for one element
        if (!$multiple) {
            $json = $json[0];
        }

        $json = dj_json_encode($json);
    } elseif ($json === null) {
        if (in_array($type, array('contests','state'))) {
            $url = $endpoint['url'];
        } else {
            $url = $endpoint['url'].'/'.$idsCombined;
        }

        // Temporary fix for single/multi contest API:
        if (isset($cid)) {
            $url = '/contests/' . rest_extid('contests', $cid) . $url;
        }

        $json = API_request($url, 'GET', '', false);
        if (empty($json) || $json==='null' || ($multiple && $json === '[]')) {
            logmsg(LOG_WARNING, "eventlog: got no JSON data from '$url'");
            // If we didn't get data from the API, then that is
            // probably because this particular data is not visible,
            // for example because it belongs to an invisible jury
            // team. If we don't have data, there's also no point in
            // trying to insert anything in the eventlog table.
            return;
        }
    }

    // Decode the JSON, because if we have passed multiple ID's,
    // we need to look up things in the JSON and always decoding
    // simplifies the structure of this function
    $json = dj_json_decode($json);

    // First acquire an advisory lock to prevent other event logging,
    // so that we can obtain a unique timestamp.
    if ($DB->q("VALUE SELECT GET_LOCK('domjudge.eventlog',1)") != 1) {
        error("eventlog: failed to obtain lock");
    }

    // Explicitly construct the time as string to prevent float
    // representation issues.
    $now = sprintf('%.3f', microtime(true));

    // TODO: can this be wrapped into a single query?
    $eventids = [];
    foreach ($cids as $cid) {
        $table = ($endpoint['tables'] ? $endpoint['tables'][0] : null);
        foreach ($dataids as $idx => $dataid) {
            if ($multiple) {
                $jsonElement = dj_json_encode($json[$idx]);
            } else {
                $jsonElement = dj_json_encode($json);
            }
            $eventid = $DB->q('RETURNID INSERT INTO event
                              (eventtime, cid, endpointtype, endpointid,
                               datatype, dataid, action, content)
                               VALUES (%s, %i, %s, %s, %s, %s, %s, %s)',
                              $now, $cid, $type, $ids[$idx], $table,
                              $dataid, $action, $jsonElement);
            $eventids[] = $eventid;
        }
    }

    if ($DB->q("VALUE SELECT RELEASE_LOCK('domjudge.eventlog')") != 1) {
        error("eventlog: failed to release lock");
    }

    if (count($eventids) !== $expectedEvents) {
        error("eventlog: failed to $action $type/$idsCombined " .
              '('.count($eventids).'/'.$expectedEvents.' events done)');
    }

    logmsg(LOG_DEBUG, "eventlog: ${action}d $type/$idsCombined " .
           'for '.count($cids).' contest(s)');
}

$resturl = $restuser = $restpass = null;

/**
 * This function is copied from judgedaemon.main.php and a quick hack.
 * We should directly call the code that generates the API response.
 */
function read_API_credentials()
{
    global $resturl, $restuser, $restpass;

    $credfile = ETCDIR . '/restapi.secret';
    $credentials = @file($credfile);
    if (!$credentials) {
        error("Cannot read REST API credentials file " . $credfile);
    }
    foreach ($credentials as $credential) {
        if ($credential{0} == '#') {
            continue;
        }
        list($endpointID, $resturl, $restuser, $restpass) = preg_split("/\s+/", trim($credential));
        if ($endpointID==='default') {
            return;
        }
    }
    $resturl = $restuser = $restpass = null;
}

/**
 * Perform a request to the REST API and handle any errors.
 * $url is the part appended to the base DOMjudge $resturl.
 * $verb is the HTTP method to use: GET, POST, PUT, or DELETE
 * $data is the urlencoded data passed as GET or POST parameters.
 * When $failonerror is set to false, any error will be turned into a
 * warning and null is returned.
 *
 * This function is duplicated from judge/judgedaemon.main.php.
 */
function API_request($url, $verb = 'GET', $data = '', $failonerror = true)
{
    global $resturl, $restuser, $restpass, $lastrequest, $G_SYMFONY, $apiFromInternal;
    if (isset($G_SYMFONY)) {
        // Perform an internal Symfony request to the API
        logmsg(LOG_DEBUG, "API internal request $verb $url");

        $apiFromInternal = true;
        $url = 'http://localhost/api'. $url;
        $httpKernel = $G_SYMFONY->getHttpKernel();
        parse_str($data, $parsedData);

        // Our API checks $_SERVER['REQUEST_METHOD'], $_GET and $_POST but Symfony does not overwrite it, so do this manually
        $origMethod = $_SERVER['REQUEST_METHOD'];
        $origPost = $_POST;
        $origGet = $_GET;
        $_POST = [];
        $_GET = [];
        // TODO: other verbs
        if ($verb === 'GET') {
            $_GET = $parsedData;
        } elseif ($verb === 'POST') {
            $_POST = $parsedData;
        }
        $_SERVER['REQUEST_METHOD'] = $verb;

        $request = \Symfony\Component\HttpFoundation\Request::create($url, $verb, $parsedData);
        $response = $httpKernel->handle($request, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);

        // Set back the request method and superglobals, if other code still wants to use it
        $_SERVER['REQUEST_METHOD'] = $origMethod;
        $_GET = $origGet;
        $_POST = $origPost;

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $errstr = "executing internal $verb request to url " . $url .
                ": http status code: " . $status . ", response: " . $response;
            if ($failonerror) {
                error($errstr);
            } else {
                logmsg(LOG_WARNING, $errstr);
                return null;
            }
        }

        return $response->getContent();
    }

    if ($resturl === null) {
        read_API_credentials();
        if ($resturl === null) {
            error("could not initialize REST API credentials");
        }
    }

    logmsg(LOG_DEBUG, "API request $verb $url");

    $url = $resturl . $url;
    if ($verb == 'GET' && !empty($data)) {
        $url .= '?' . $data;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $restuser . ":" . $restpass);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($verb == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
        }
    } elseif ($verb == 'PUT' || $verb == 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
    }
    if ($verb == 'POST' || $verb == 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $errstr = "Error while executing curl $verb to url " . $url . ": " . curl_error($ch);
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            return null;
        }
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 || $status >= 300) {
        $errstr = "executing internal $verb request to url " . $url .
            ": http status code: " . $status . ", response: " . $response;
        if ($failonerror) {
            error($errstr);
        } else {
            logmsg(LOG_WARNING, $errstr);
            return null;
        }
    }

    curl_close($ch);
    return $response;
}

/**
 * Convert PHP ini values to bytes, as per
 * http://www.php.net/manual/en/function.ini-get.php
 */
function phpini_to_bytes($size_str)
{
    switch (substr($size_str, -1)) {
        case 'M': case 'm': return (int)$size_str * 1048576;
        case 'K': case 'k': return (int)$size_str * 1024;
        case 'G': case 'g': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}

// Color names as defined by https://www.w3.org/TR/css3-color/#html4
$HTML_colors = array(
"black" => "#000000",
"silver" => "#C0C0C0",
"gray" => "#808080",
"white" => "#FFFFFF",
"maroon" => "#800000",
"red" => "#FF0000",
"purple" => "#800080",
"fuchsia" => "#FF00FF",
"green" => "#008000",
"lime" => "#00FF00",
"olive" => "#808000",
"yellow" => "#FFFF00",
"navy" => "#000080",
"blue" => "#0000FF",
"teal" => "#008080",
"aqua" => "#00FFFF",
"aliceblue" => "#f0f8ff",
"antiquewhite" => "#faebd7",
"aqua" => "#00ffff",
"aquamarine" => "#7fffd4",
"azure" => "#f0ffff",
"beige" => "#f5f5dc",
"bisque" => "#ffe4c4",
"black" => "#000000",
"blanchedalmond" => "#ffebcd",
"blue" => "#0000ff",
"blueviolet" => "#8a2be2",
"brown" => "#a52a2a",
"burlywood" => "#deb887",
"cadetblue" => "#5f9ea0",
"chartreuse" => "#7fff00",
"chocolate" => "#d2691e",
"coral" => "#ff7f50",
"cornflowerblue" => "#6495ed",
"cornsilk" => "#fff8dc",
"crimson" => "#dc143c",
"cyan" => "#00ffff",
"darkblue" => "#00008b",
"darkcyan" => "#008b8b",
"darkgoldenrod" => "#b8860b",
"darkgray" => "#a9a9a9",
"darkgreen" => "#006400",
"darkgrey" => "#a9a9a9",
"darkkhaki" => "#bdb76b",
"darkmagenta" => "#8b008b",
"darkolivegreen" => "#556b2f",
"darkorange" => "#ff8c00",
"darkorchid" => "#9932cc",
"darkred" => "#8b0000",
"darksalmon" => "#e9967a",
"darkseagreen" => "#8fbc8f",
"darkslateblue" => "#483d8b",
"darkslategray" => "#2f4f4f",
"darkslategrey" => "#2f4f4f",
"darkturquoise" => "#00ced1",
"darkviolet" => "#9400d3",
"deeppink" => "#ff1493",
"deepskyblue" => "#00bfff",
"dimgray" => "#696969",
"dimgrey" => "#696969",
"dodgerblue" => "#1e90ff",
"firebrick" => "#b22222",
"floralwhite" => "#fffaf0",
"forestgreen" => "#228b22",
"fuchsia" => "#ff00ff",
"gainsboro" => "#dcdcdc",
"ghostwhite" => "#f8f8ff",
"gold" => "#ffd700",
"goldenrod" => "#daa520",
"gray" => "#808080",
"green" => "#008000",
"greenyellow" => "#adff2f",
"grey" => "#808080",
"honeydew" => "#f0fff0",
"hotpink" => "#ff69b4",
"indianred" => "#cd5c5c",
"indigo" => "#4b0082",
"ivory" => "#fffff0",
"khaki" => "#f0e68c",
"lavender" => "#e6e6fa",
"lavenderblush" => "#fff0f5",
"lawngreen" => "#7cfc00",
"lemonchiffon" => "#fffacd",
"lightblue" => "#add8e6",
"lightcoral" => "#f08080",
"lightcyan" => "#e0ffff",
"lightgoldenrodyellow" => "#fafad2",
"lightgray" => "#d3d3d3",
"lightgreen" => "#90ee90",
"lightgrey" => "#d3d3d3",
"lightpink" => "#ffb6c1",
"lightsalmon" => "#ffa07a",
"lightseagreen" => "#20b2aa",
"lightskyblue" => "#87cefa",
"lightslategray" => "#778899",
"lightslategrey" => "#778899",
"lightsteelblue" => "#b0c4de",
"lightyellow" => "#ffffe0",
"lime" => "#00ff00",
"limegreen" => "#32cd32",
"linen" => "#faf0e6",
"magenta" => "#ff00ff",
"maroon" => "#800000",
"mediumaquamarine" => "#66cdaa",
"mediumblue" => "#0000cd",
"mediumorchid" => "#ba55d3",
"mediumpurple" => "#9370db",
"mediumseagreen" => "#3cb371",
"mediumslateblue" => "#7b68ee",
"mediumspringgreen" => "#00fa9a",
"mediumturquoise" => "#48d1cc",
"mediumvioletred" => "#c71585",
"midnightblue" => "#191970",
"mintcream" => "#f5fffa",
"mistyrose" => "#ffe4e1",
"moccasin" => "#ffe4b5",
"navajowhite" => "#ffdead",
"navy" => "#000080",
"oldlace" => "#fdf5e6",
"olive" => "#808000",
"olivedrab" => "#6b8e23",
"orange" => "#ffa500",
"orangered" => "#ff4500",
"orchid" => "#da70d6",
"palegoldenrod" => "#eee8aa",
"palegreen" => "#98fb98",
"paleturquoise" => "#afeeee",
"palevioletred" => "#db7093",
"papayawhip" => "#ffefd5",
"peachpuff" => "#ffdab9",
"peru" => "#cd853f",
"pink" => "#ffc0cb",
"plum" => "#dda0dd",
"powderblue" => "#b0e0e6",
"purple" => "#800080",
"red" => "#ff0000",
"rosybrown" => "#bc8f8f",
"royalblue" => "#4169e1",
"saddlebrown" => "#8b4513",
"salmon" => "#fa8072",
"sandybrown" => "#f4a460",
"seagreen" => "#2e8b57",
"seashell" => "#fff5ee",
"sienna" => "#a0522d",
"silver" => "#c0c0c0",
"skyblue" => "#87ceeb",
"slateblue" => "#6a5acd",
"slategray" => "#708090",
"slategrey" => "#708090",
"snow" => "#fffafa",
"springgreen" => "#00ff7f",
"steelblue" => "#4682b4",
"tan" => "#d2b48c",
"teal" => "#008080",
"thistle" => "#d8bfd8",
"tomato" => "#ff6347",
"turquoise" => "#40e0d0",
"violet" => "#ee82ee",
"wheat" => "#f5deb3",
"white" => "#ffffff",
"whitesmoke" => "#f5f5f5",
"yellow" => "#ffff00",
"yellowgreen" => "#9acd32",
);

/**
 * Convert a HTML extended color name to 6-digit hex RGB value.
 * If $color is already in hex RGB format, it is returned unchanged.
 * Returns NULL if $color is not valid.
 */
function color_to_hex($color)
{
    global $HTML_colors;

    if (preg_match('/^#([[:xdigit:]]{3}){1,2}$/', $color)) {
        return $color;
    }

    $color = strtolower(preg_replace('/[[:space:]]/', '', $color));
    if (isset($HTML_colors[$color])) {
        return strtoupper($HTML_colors[$color]);
    }
    return null;
}

/**
 * Convert a hexadecimal RGB color code to the closest HTML color
 * name. Returns NULL if $hex is not a valid 3 or 6 digit hex RGB
 * string starting with a '#'.
 */
function hex_to_color($hex)
{
    global $HTML_colors;

    // Expand short 3 digit hex version.
    if (preg_match('/^#[[:xdigit:]]{3}$/', $hex)) {
        $new = '#';
        for ($i=1; $i<=3; $i++) {
            $new .= str_repeat($hex[$i], 2);
        }
        $hex = $new;
    }
    if (!preg_match('/^#[[:xdigit:]]{6}$/', $hex)) {
        return null;
    }

    // Find the best match in L1 distance.
    $bestmatch = '';
    $bestdist = 999999;

    foreach ($HTML_colors as $color => $rgb) {
        $dist = 0;
        for ($i=1; $i<=3; $i++) {
            sscanf(substr($hex, 2*$i-1, 2), '%x', $val1);
            sscanf(substr($rgb, 2*$i-1, 2), '%x', $val2);
            $dist += abs($val1 - $val2);
        }
        if ($dist<$bestdist) {
            $bestdist = $dist;
            $bestmatch = $color;
        }
    }

    return $bestmatch;
}
