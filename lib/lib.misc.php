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

/**
 * Wrapper around PHP setcookie function to automatically set some
 * DOMjudge specific defaults and check the return value.
 * - cookies are defined in a common path for all web interfaces
 */
function dj_setcookie($name, $value = null, $expire = 0,
                      $path = null, $domain = null, $secure = false, $httponly = false)
{
	if ( !isset($path) ) {
		// KLUDGE: We want to find the DOMjudge base path, but this
		// information is not directly available as configuration, so
		// we extract it from the executed PHP script.
		$path = preg_replace('/(jury|public|team)\/?$/', '',
		                     dirname($_SERVER['PHP_SELF']));
	}

	$ret = setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);

	if ( $ret!==true ) warning("Cookie '$name' not set properly.");

	logmsg(LOG_DEBUG, "Cookie set: $name=$value, expire=$expire, path=$path");

	return $ret;
}

/**
 * helperfunction to read all contents from a file.
 * If $sizelimit is true (default), then only limit this to
 * the first 50,000 bytes and attach a note saying so.
 */
function getFileContents($filename, $sizelimit = true) {

	if ( ! file_exists($filename) ) {
		return '';
	}
	if ( ! is_readable($filename) ) {
		error("Could not open $filename for reading: not readable");
	}

	if ( $sizelimit && filesize($filename) > 50000 ) {
		return file_get_contents($filename, FALSE, NULL, -1, 50000)
			. "\n[output truncated after 50,000 B]\n";
	}

	return file_get_contents($filename);
}

/**
 * Will return all the contests that are currently active
 * When fulldata is true, returns the total row as an array
 * instead of just the ID (array indices will be contest ID's then).
 * If $onlyofteam is not null, only show contests that team is part
 * of. If it is -1, only show publicly visible contests
 * If $alsofuture is true, also show the contests that start in the future
 * The results will have the value of field $key in the database as key
 */
function getCurContests($fulldata = FALSE, $onlyofteam = NULL,
                        $alsofuture = FALSE, $key = 'cid')
{
	global $DB;
	if ( $alsofuture ) {
		$extra = '';
	} else {
		$extra = 'AND activatetime <= UNIX_TIMESTAMP()';
	}
	if ( $onlyofteam !== null && $onlyofteam > 0 ) {
		$contests = $DB->q("SELECT * FROM contest
		                    LEFT JOIN contestteam USING (cid)
		                    WHERE (contestteam.teamid = %i OR contest.public = 1)
		                    AND enabled = 1 ${extra}
		                    AND ( deactivatetime IS NULL OR
		                          deactivatetime > UNIX_TIMESTAMP() )
		                    ORDER BY activatetime", $onlyofteam);
	} elseif ( $onlyofteam === -1 ) {
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
	if ( !$fulldata ) {
		return array_keys($contests);
	}

	return $contests;
}

/**
 * Parse 'id' from HTTP GET or POST variables and check that it is a
 * valid number, or string consisting of IDENTIFIER_CHARS.
 *
 * Returns id as int or string, or NULL if none found.
 */
function getRequestID($numeric = TRUE)
{
	if ( empty($_REQUEST['id']) ) return NULL;

	$id = $_REQUEST['id'];
	if ( $numeric ) {
		if ( !preg_match('/^[0-9]+$/', $id) ) {
			error("Identifier specified is not a number");
		}
		return (int)$id;
	} else {
		if ( !preg_match('/^' . IDENTIFIER_CHARS . '*$/',$id) ) {
			error("Identifier specified contains invalid characters");
		}
		return $id;
	}

	// This should never happen:
	error("Could not parse identifier");
}

/**
 * Returns whether the problem with probid is visible to teams and the
 * public. That is, it is in the active contest, which has started and
 * it is submittable.
 */
function problemVisible($probid)
{
	global $DB, $cdata;

	if ( empty($probid) ) return FALSE;
	if ( !$cdata || difftime(now(),$cdata['starttime']) < 0 ) return FALSE;

	return $DB->q('MAYBETUPLE SELECT probid FROM problem
	               INNER JOIN contestproblem USING (probid)
	               WHERE cid = %i AND allow_submit = 1 AND probid = %i',
	              $cdata['cid'], $probid) !== NULL;
}

/**
 * Calculate contest time from wall-clock time.
 * Returns time since contest start in seconds.
 * This function is currently a stub around timediff, but introduced
 * to allow minimal changes wrt. the removed intervals required for
 * the ICPC specification.
 */
function calcContestTime($walltime, $cid)
{
	// get contest data in case of non-public contests
	$cdatas = getCurContests(TRUE);

	$contesttime = difftime($walltime, $cdatas[$cid]['starttime']);

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
function calcScoreRow($cid, $team, $prob) {
	global $DB;

	logmsg(LOG_DEBUG, "calcScoreRow '$cid' '$team' '$prob'");

	// First acquire an advisory lock to prevent other calls to
	// calcScoreRow() from interfering with our update.
	$lockstr = "domjudge.$cid.$team.$prob";
	if ( $DB->q("VALUE SELECT GET_LOCK('$lockstr',3)") != 1 ) {
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
	                 ( dbconfig_get('compile_penalty', 1) ? "" :
	                   "AND j.result != 'compiler-error' ") .
	                 'AND submittime < c.endtime
	                  ORDER BY submittime',
	                 $team, $prob, $cid);

	// reset vars
	$submitted_j = $pending_j = $time_j = $correct_j = 0;
	$submitted_p = $pending_p = $time_p = $correct_p = 0;

	// for each submission
	while( $row = $result->next() ) {

		// Contest submit time in minutes for scoring.
		$submittime = (int)floor(calcContestTime($row['submittime'],$cid) / 60);

		// Check if this submission has a publicly visible judging result:
		if ( (dbconfig_get('verification_required', 0) && ! $row['verified']) ||
		     empty($row['result']) ) {

			$pending_j++;
			$pending_p++;
			// Don't do any more counting for this submission.
			continue;
		}

		$submitted_j++;
		if ( $row['afterfreeze'] ) {
			// Show submissions after freeze as pending to the public
			// (if SHOW_PENDING is enabled):
			$pending_p++;
		} else {
			$submitted_p++;
		}

		// if correct, don't look at any more submissions after this one
		if ( $row['result'] == 'correct' ) {

			$correct_j = 1;
			$time_j = $submittime;
			if ( ! $row['afterfreeze'] ) {
				$correct_p = 1;
				$time_p = $submittime;
			}
			// stop counting after a first correct submission
			break;
		}
	}

	// insert or update the values in the public/team scores table
	$DB->q('REPLACE INTO scorecache_public
	        (cid, teamid, probid, submissions, pending, totaltime, is_correct)
	        VALUES (%i,%i,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_p, $pending_p, $time_p, $correct_p);

	// insert or update the values in the jury scores table
	$DB->q('REPLACE INTO scorecache_jury
	        (cid, teamid, probid, submissions, pending, totaltime, is_correct)
	        VALUES (%i,%i,%i,%i,%i,%i,%i)',
	       $cid, $team, $prob, $submitted_j, $pending_j, $time_j, $correct_j);

	if ( $DB->q("VALUE SELECT RELEASE_LOCK('$lockstr')") != 1 ) {
		error("calcScoreRow failed to release lock '$lockstr'");
	}

	// If we found a new correct result, update the rank cache too
	if ( $correct_j > 0 ) {
		updateRankCache($cid, $team, true);
	}
	if ( $correct_p > 0 ) {
		updateRankCache($cid, $team, false);
	}

	return;
}

/**
 * Update tables used for efficiently computing team ranks
 *
 * Given a contestid and teamid (re)calculate the time
 * and solved problems for a team. Third parameter indictates
 * if the cache for jury or public should be updated.
 *
 * Due to current transactions usage, this function MUST NOT contain
 * any START TRANSACTION or COMMIT statements.
 */
function updateRankCache($cid, $team, $jury) {
	global $DB;

	logmsg(LOG_DEBUG, "updateRankCache '$cid' '$team' '$jury'");

	$team_penalty = $DB->q("VALUE SELECT penalty FROM team WHERE teamid = %i", $team);

	// Find table name
	$tblname = $jury ? 'jury' : 'public';

	// First acquire an advisory lock to prevent other calls to
	// calcScoreRow() from interfering with our update.
	$lockstr = "domjudge.$cid.$team.$tblname";
	if ( $DB->q("VALUE SELECT GET_LOCK('$lockstr',3)") != 1 ) {
		error("updateRankCache failed to obtain lock '$lockstr'");
	}

	// Fetch values from scoreboard cache per problem
	$scoredata = $DB->q("SELECT submissions, is_correct, cp.points, totaltime
			     FROM scorecache_$tblname
			     LEFT JOIN contestproblem cp USING(probid,cid)
			     WHERE cid = %i and teamid = %i", $cid, $team);
	$num_points = 0;
	$total_time = $team_penalty;
	while ( $srow = $scoredata->next() ) {
		// Only count solved problems
		if ( $srow['is_correct'] ) {
			$penalty = calcPenaltyTime( $srow['is_correct'],
			                            $srow['submissions'] );
			$num_points += $srow['points'];
			$total_time += $srow['totaltime'] + $penalty;
		}
	}

	// Update the rank cache table
	$DB->q("REPLACE INTO rankcache_$tblname
	        (cid, teamid, points, totaltime)
	        VALUES (%i,%i,%i,%i)",
	       $cid, $team, $num_points, $total_time);

	// Release the lock
	if ( $DB->q("VALUE SELECT RELEASE_LOCK('$lockstr')") != 1 ) {
		error("updateRankCache failed to release lock '$lockstr'");
	}
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
	if ( ! $solved ) return 0;

	return ( $num_submissions - 1 ) * dbconfig_get('penalty_time', 20);
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
	if ( empty($results_prio) ) {
		$results_prio  = dbconfig_get('results_prio');
	}

	// Whether we have NULL results
	$havenull = FALSE;

	// This stores the current result and priority to be returned:
	$bestres  = NULL;
	$bestprio = -1;

	// Find first highest priority result:
	foreach ( $runresults as $tc => $res ) {
		if ( $res===NULL ) {
			$havenull = TRUE;
		} else {
			$prio = $results_prio[$res];
			if ( empty($prio) ) error("Unknown result '$res' found.");
			if ( $prio>$bestprio ) {
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
	if ( $havenull && $bestprio<$maxprio ) return NULL;

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
	if ( count($tokens)!=1 && count($tokens)!=3 ) {
		error("invalid timelimit overshoot string '$overshoot_cfg'");
	}

	$val1 = overshoot_parse($timelimit, $tokens[0]);
	if ( count($tokens)==1 ) return $val1;

	$val2 = overshoot_parse($timelimit, $tokens[2]);
	switch ( $tokens[1] ) {
	case '+': return $val1 + $val2;
	case '|': return max($val1,$val2);
	case '&': return min($val1,$val2);
	}
	error("invalid timelimit overshoot string '$overshoot_cfg'");
}

/**
 * Helper function for overshoot_time(), returns overshoot for single token.
 */
function overshoot_parse($timelimit, $token)
{
	$res = sscanf($token,'%d%c%n');
	if ( count($res)!=3 ) error("invalid timelimit overshoot token '$token'");
	list($val,$type,$len) = $res;
	if ( strlen($token)!=$len ) error("invalid timelimit overshoot token '$token'");

	if ( $val<0 ) error("timelimit overshoot cannot be negative: '$token'");
	switch ( $type ) {
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
	return microtime(TRUE);
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
 * Compares two IP addresses for equivalence
 */
function compareipaddr($ip1, $ip2)
{
	$ip1n = inet_pton($ip1); $ip2n = inet_pton($ip2);
	return $ip1n !== FALSE && $ip1n === $ip2n;
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler($signal)
{
	global $exitsignalled;

	logmsg(LOG_DEBUG, "Signal $signal received");

	switch ( $signal ) {
	case SIGTERM:
	case SIGHUP:
	case SIGINT:
		$exitsignalled = TRUE;
	}
}

function initsignals()
{
	global $exitsignalled;

	$exitsignalled = FALSE;

	if ( ! function_exists('pcntl_signal') ) {
		logmsg(LOG_INFO, "Signal handling not available");
		return;
	}

	logmsg(LOG_DEBUG, "Installing signal handlers");

	// Install signal handler for TERMINATE, HANGUP and INTERRUPT
	// signals. The sleep() call will automatically return on
	// receiving a signal.
	pcntl_signal(SIGTERM,"sig_handler");
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
function daemonize($pidfile = NULL)
{
	switch ( $pid = pcntl_fork() ) {
	case -1: error("cannot fork daemon");
	case  0: break; // child process: do nothing here.
	default: exit;  // parent process: exit.
	}

	if ( ($pid = posix_getpid())===FALSE ) error("failed to obtain PID");

	// Check and write PID to file
	if ( !empty($pidfile) ) {
		if ( ($fd=@fopen($pidfile, 'x+'))===FALSE ) {
			error("cannot create pidfile '$pidfile'");
		}
		$str = "$pid\n";
		if ( @fwrite($fd, $str)!=strlen($str) ) {
			error("failed writing PID to file");
		}
		register_shutdown_function('unlink', $pidfile);
	}

	// Notify user with daemon PID before detaching from TTY.
	logmsg(LOG_NOTICE, "daemonizing with PID = $pid");

	// Close std{in,out,err} file descriptors
	if ( !fclose(STDIN ) || !($GLOBALS['STDIN']  = fopen('/dev/null', 'r')) ||
	     !fclose(STDOUT) || !($GLOBALS['STDOUT'] = fopen('/dev/null', 'w')) ||
	     !fclose(STDERR) || !($GLOBALS['STDERR'] = fopen('/dev/null', 'w')) ) {
		error("cannot reopen stdio files to /dev/null");
	}

	// FIXME: We should really close all other open file descriptors
	// here, but PHP does not support this.

	// Start own process group, detached from any tty
	if ( posix_setsid()<0 ) error("cannot set daemon process group");
}

/**
 * This function takes a (set of) temporary file(s) of a submission,
 * validates it and puts it into the database. Additionally it
 * moves it to a backup storage.
 */
function submit_solution($team, $prob, $contest, $lang, $files, $filenames, $origsubmitid = NULL)
{
	global $DB;

	if( empty($team) ) error("No value for Team.");
	if( empty($prob) ) error("No value for Problem.");
	if( empty($contest) ) error("No value for Contest.");
	if( empty($lang) ) error("No value for Language.");

	if ( !is_array($files) || count($files)==0 ) error("No files specified.");
	if ( count($files) > dbconfig_get('sourcefiles_limit',100) ) {
		error("Tried to submit more than the allowed number of source files.");
	}
	if ( !is_array($filenames) || count($filenames)!=count($files) ) {
		error("Nonmatching (number of) filenames specified.");
	}

	if ( count($filenames)!=count(array_unique($filenames)) ) {
		error("Duplicate filenames detected.");
	}

	$sourcesize = dbconfig_get('sourcesize_limit');

	// If no contest has started yet, refuse submissions.
	$now = now();

	$contestdata = $DB->q('MAYBETUPLE SELECT starttime,endtime FROM contest WHERE cid = %i', $contest);
	if ( ! isset($contestdata) ) {
		error("Contest c$contest not found.");
	}
	if( difftime($contestdata['starttime'], $now) > 0 ) {
		error("The contest is closed, no submissions accepted. [c$contest]");
	}

	// Check 2: valid parameters?
	if( ! $langid = $DB->q('MAYBEVALUE SELECT langid FROM language
	                        WHERE langid = %s AND allow_submit = 1', $lang) ) {
		error("Language '$lang' not found in database or not submittable.");
	}
	if( ! $teamid = $DB->q('MAYBEVALUE SELECT teamid FROM team
	                        WHERE teamid = %i AND enabled = 1',$team) ) {
		error("Team '$team' not found in database or not enabled.");
	}
	$probdata = $DB->q('MAYBETUPLE SELECT probid, points FROM problem
	                    INNER JOIN contestproblem USING (probid)
	                    WHERE probid = %s AND cid = %i AND allow_submit = 1',
	                   $prob, $contest);

	if ( empty($probdata) ) {
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
	for($i=0; $i<count($files); $i++) {
		if ( ! is_readable($files[$i]) ) {
			error("File '".$files[$i]."' not found (or not readable).");
		}
		if ( ! preg_match(FILENAME_REGEX, $filenames[$i]) ) {
			error("Illegal filename '".$filenames[$i]."'.");
		}
		$totalsize += filesize($files[$i]);
	}
	if ( $totalsize > $sourcesize*1024 ) {
		error("Submission file(s) are larger than $sourcesize kB.");
	}

	logmsg (LOG_INFO, "input verified");

	// Insert submission into the database
	$id = $DB->q('RETURNID INSERT INTO submission
	              (cid, teamid, probid, langid, submittime, origsubmitid)
	              VALUES (%i, %i, %i, %s, %s, %i)',
	             $contest, $teamid, $probid, $langid, $now, $origsubmitid);

	for($rank=0; $rank<count($files); $rank++) {
		$DB->q('INSERT INTO submission_file
		        (submitid, filename, rank, sourcecode) VALUES (%i, %s, %i, %s)',
		       $id, $filenames[$rank], $rank, getFileContents($files[$rank], false));
	}

	// Recalculate scoreboard cache for pending submissions
	calcScoreRow($contest, $teamid, $probid);

	// Log to event table
	$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid, submitid, description)
	        VALUES(%s, %i, %i, %s, %i, %i, "problem submitted")',
	       now(), $contest, $teamid, $langid, $probid, $id);

	if ( is_writable( SUBMITDIR ) ) {
		// Copy the submission to SUBMITDIR for safe-keeping
		for($rank=0; $rank<count($files); $rank++) {
			$fdata = array('cid' => $contest,
			               'submitid' => $id,
			               'teamid' => $teamid,
			               'probid' => $probid,
			               'langid' => $langid,
			               'rank' => $rank,
			               'filename' => $filenames[$rank]);
			$tofile = SUBMITDIR . '/' . getSourceFilename($fdata);
			if ( ! @copy($files[$rank], $tofile) ) {
				warning("Could not copy '" . $files[$rank] . "' to '" . $tofile . "'");
			}
		}
	} else {
		logmsg(LOG_DEBUG, "SUBMITDIR not writable, skipping");
	}

	if( difftime($contestdata['endtime'], $now) <= 0 ) {
		logmsg(LOG_INFO, "The contest is closed, submission stored but not processed. [c$contest]");
	}

	return $id;
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

	foreach( $lines as $line ) {
		// Check for quoted lines
		if ( strspn($line,$quote)>0 ) {
			// First append unquoted text wrapped, then quoted line:
			$result .= wordwrap($unquoted,$width);
			$unquoted = '';
			$result .= $line . "\n";
		} else {
			$unquoted .= $line . "\n";
		}
	}

	$result .= wordwrap(rtrim($unquoted),$width);

	return $result;
}

/**
 * Log an action to the auditlog table.
 */
function auditlog($datatype, $dataid, $action, $extrainfo = null,
                  $force_username = null, $cid = null)
{
	global $username, $DB;

	if ( !empty($force_username) ) {
		$user = $force_username;
	} else {
		$user = $username;
	}

	$DB->q('INSERT INTO auditlog
	        (logtime, cid, user, datatype, dataid, action, extrainfo)
	        VALUES(%s, %i, %s, %s, %s, %s, %s)',
	       now(), $cid, $user, $datatype, $dataid, $action, $extrainfo);
}

/**
 * Convert PHP ini values to bytes, as per
 * http://www.php.net/manual/en/function.ini-get.php
 */
function phpini_to_bytes($size_str) {
	switch (substr ($size_str, -1))
	{
		case 'M': case 'm': return (int)$size_str * 1048576;
		case 'K': case 'k': return (int)$size_str * 1024;
		case 'G': case 'g': return (int)$size_str * 1073741824;
		default: return $size_str;
	}
}

