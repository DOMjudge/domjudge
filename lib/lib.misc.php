<?php declare(strict_types=1);
/**
 * Miscellaneous helper functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

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
    bool $fulldata = false,
    $onlyofteam = null,
    bool $alsofuture = false,
    string $key = 'cid'
) : array {
    global $DB;
    if ($alsofuture) {
        $extra = '';
    } else {
        $extra = 'AND activatetime <= UNIX_TIMESTAMP()';
    }
    if ($onlyofteam !== null && $onlyofteam > 0) {
        $contests = $DB->q("SELECT * FROM contest
                            LEFT JOIN contestteam USING (cid)
                            WHERE (contestteam.teamid = %i OR contest.open_to_all_teams = 1)
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
function getContest(int $cid) : array
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
 * Calculate contest time from wall-clock time (and removed intervals).
 * Returns time since contest start in seconds.
 * NOTE: It is assumed that removed intervals do not overlap and that
 * they all fall within the contest start and end times.
 */
function calcContestTime(float $walltime, int $cid) : float
{
    $cdata = getContest($cid);

    $contesttime = difftime($walltime, (float)$cdata['starttime']);

    if (ALLOW_REMOVED_INTERVALS) {
        foreach ($cdata['removed_intervals'] as $intv) {
            if (difftime($intv['starttime'], $walltime)<0) {
                $contesttime -= min(
                    difftime($walltime, (float)$intv['starttime']),
                    difftime((float)$intv['endtime'], (float)$intv['starttime'])
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
function calcScoreRow(int $cid, int $team, int $prob, bool $updateRankCache = true)
{
    /** @var \App\Service\DOMJudgeService $G_SYMFONY */
    /** @var \App\Service\ScoreboardService $G_SCOREBOARD_SERVICE */
    global $G_SYMFONY, $G_SCOREBOARD_SERVICE;

    if (isset($G_SCOREBOARD_SERVICE)) {
        $contest = $G_SYMFONY->getContest($cid);
        $team    = $G_SYMFONY->getTeam($team);
        $problem = $G_SYMFONY->getProblem($prob);
        if (!$contest || !$team || !$problem) {
            return;
        }
        $G_SCOREBOARD_SERVICE->calculateScoreRow($contest, $team, $problem, $updateRankCache);
        return;
    }
    // Fallback to non-Symfony code if we are not in a Symfony context (i.e. in external tools)

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
        $submittime = calcContestTime((float)$row['submittime'], $cid);

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
    if ($updateRankCache && ($correct_j > 0 || $correct_p > 0)) {
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
function updateRankCache(int $cid, int $team)
{
    /** @var \App\Service\DOMJudgeService $G_SYMFONY */
    /** @var \App\Service\ScoreboardService $G_SCOREBOARD_SERVICE */
    global $G_SYMFONY, $G_SCOREBOARD_SERVICE;

    if (isset($G_SCOREBOARD_SERVICE)) {
        $contest = $G_SYMFONY->getContest($cid);
        $team    = $G_SYMFONY->getTeam($team);
        if (!$contest || !$team) {
            return;
        }
        $G_SCOREBOARD_SERVICE->updateRankCache($contest, $team);
        return;
    }
    // Fallback to non-Symfony code if we are not in a Symfony context (i.e. in external tools)

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
            if ($srow['points'] !== null && $srow['is_correct_'.$variant]) {
                $penalty = calcPenaltyTime(
                    (bool)$srow['is_correct_'.$variant],
                    (int)$srow['submissions_'.$variant]
                );
                $num_points[$variant] += $srow['points'];
                $total_time[$variant] += scoretime((float)$srow['solvetime_'.$variant]) + $penalty;
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
 * Time as used on the scoreboard (i.e. truncated minutes or seconds,
 * depending on the scoreboard resolution setting).
 */
function scoretime(float $time)
{
    if (dbconfig_get('score_in_seconds', 0)) {
        $result = (int) floor($time);
    } else {
        $result = (int) floor($time / 60);
    }
    return $result;
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

function calcPenaltyTime(bool $solved, int $num_submissions) : int
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

/**
 * Calculate timelimit overshoot from actual timelimit and configured
 * overshoot that can be specified as a sum,max,min of absolute and
 * relative times. Returns overshoot seconds as a float.
 */
function overshoot_time(float $timelimit, string $overshoot_cfg) : float
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
function overshoot_parse(float $timelimit, string $token) : float
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
function now() : float
{
    return microtime(true);
}

/**
 * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
 * Returned value is time difference in seconds.
 */
function difftime(float $time1, float $time2) : float
{
    return $time1 - $time2;
}

/**
 * Call alert plugin program to perform user configurable action on
 * important system events. See default alert script for more details.
 */
function alert(string $msgtype, string $description = '')
{
    system(LIBDIR . "/alert '$msgtype' '$description' &");
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler(int $signal, $siginfo = null)
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
 * Compute the filename of a given submission. $fdata must be an array
 * that contains the data from submission and submission_file.
 */
function getSourceFilename(array $fdata) : string
{
    return implode('.', array('c'.$fdata['cid'], 's'.$fdata['submitid'],
                              't'.$fdata['teamid'], 'p'.$fdata['probid'], $fdata['langid'],
                              $fdata['rank'], $fdata['filename']));
}

/**
 * Output generic version information and exit.
 */
function version() : string
{
    echo SCRIPT_ID . " -- part of DOMjudge version " . DOMJUDGE_VERSION . "\n" .
        "Written by the DOMjudge developers\n\n" .
        "DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n" .
        "are welcome to redistribute it under certain conditions.  See the GNU\n" .
        "General Public Licence for details.\n";
    exit(0);
}

/**
 * Log an action to the auditlog table.
 */
function auditlog(
    string $datatype,
    $dataid,
    string $action,
    $extrainfo = null,
    $force_username = null,
    $cid = null
) {
    /** @var \App\Service\DOMJudgeService $G_SYMFONY */
    global $G_SYMFONY;

    if (isset($G_SYMFONY)) {
        $G_SYMFONY->auditlog($datatype, $dataid, $action, $extrainfo, $force_username, $cid);
        return;
    }

    // When this function is called outside of Symfony (i.e. from a commandline tool), do it the old way
    exit;
    global $username, $DB;

    if (!empty($force_username)) {
        $user = $force_username;
    } else {
        $user = $username;
    }

    $DB->q('INSERT INTO auditlog
            (logtime, cid, user, datatype, dataid, action, extrainfo)
            VALUES(%f, %i, %s, %s, %s, %s, %s)',
           now(), $cid, $user, $datatype, (string)$dataid, $action, $extrainfo);
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
function rest_extid(string $endpoint, $intid)
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
function eventlog(string $type, $dataids, string $action, $cid = null, $json = null, $ids = null)
{
    /** @var \App\Service\EventLogService $G_EVENT_LOG */
    global $G_EVENT_LOG;

    if (isset($G_EVENT_LOG)) {
        $G_EVENT_LOG->log($type, $dataids, $action, $cid, $json, $ids);
        return;
    }
    // Fallback to non-Symfony code if we are not in a Symfony context (i.e. in restore_sources2db and simulate_contest)

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

    $jsonPassed = isset($json);

    // Make a combined string to keep track of the data ID's
    $dataidsCombined = json_encode($dataids);
    $idsCombined = $ids === null ? null : is_array($ids) ? json_encode($ids) : $ids;

    logmsg(LOG_DEBUG, "eventlog arguments: '$type' '$dataidsCombined' '$action' '$cid' '$json' '$idsCombined'");

    $actions = ['create', 'update', 'delete'];

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
        } elseif ($type==='contests') {
            $cids = $dataids;
            $expectedEvents = count($dataids);
            if (count($cids)>1) {
                logmsg(LOG_WARNING, "eventlog: cannot handle multiple contests in single request");
                return;
            }
            $cid = $cids[0];
        } else {
            $cids = getCurContests();
            $expectedEvents = count($dataids) * count($cids);
        }
    }
    if (count($cids)==0) {
        logmsg(LOG_INFO, "eventlog: no active contests associated to update.");
        return;
    }

    $query = http_build_query(['ids' => $ids]);

    // Generate JSON content if not set, for deletes this is only the ID.
    if ($action === 'delete') {
        $json = array_values(array_map(function ($id) {
            return ['id' => $id];
        }, $ids));

        $json = dj_json_encode($json);
    } elseif ($json === null) {
        $url = $endpoint['url'];

        // Temporary fix for single/multi contest API:
        if (isset($cid)) {
            $url = '/contests/' . rest_extid('contests', $cid) . $url;
        }

        if (in_array($type, ['contests','state'])) {
            $data = '';
        } else {
            $data = $query;
        }

        $json = API_request($url, 'GET', $data, false, true);
        if (empty($json) || $json==='null' || $json === '[]') {
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
        foreach ($dataids as $idx => $dataid) {
            if (in_array($type, ['contests','state']) || $jsonPassed) {
                // Contest and state endpoint are singular
                $jsonElement = dj_json_encode($json);
            } else {
                $jsonElement = dj_json_encode($json[$idx]);
            }
            $eventid = $DB->q('RETURNID INSERT INTO event
                              (eventtime, cid, endpointtype, endpointid,
                               action, content)
                               VALUES (%s, %i, %s, %s, %s, %s)',
                              $now, $cid, $type, (string)$ids[$idx],
                              $action, $jsonElement);
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
 * When $asadmin is true and we are doing an internal request (i.e. $G_SYMFONY is defined) perform all requests as an admin
 *
 * This function is duplicated from judge/judgedaemon.main.php.
 */
function API_request(string $url, string $verb = 'GET', string $data = '', bool $failonerror = true, bool $asadmin = false)
{
    global $resturl, $restuser, $restpass, $lastrequest, $G_SYMFONY, $apiFromInternal;
    if (isset($G_SYMFONY)) {
        /** @var \App\Service\DOMJudgeService $G_SYMFONY */
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

        $G_SYMFONY->withAllRoles(function() use ($httpKernel, $parsedData, $verb, $url, &$response) {
            $request  = \Symfony\Component\HttpFoundation\Request::create($url, $verb, $parsedData);
            $response = $httpKernel->handle($request, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
        });

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
