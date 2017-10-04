<?php
/**
 * DOMjudge public REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

define('DOMJUDGE_API_VERSION', 4);

require('init.php');
require_once(LIBWWWDIR . '/common.jury.php');
use DOMJudgeBundle\Utils\Utils;

function infreeze($cdata, $time)
{

	if ( ( ! empty($cdata['freezetime']) &&
		difftime($time, $cdata['freezetime'])>0 ) &&
		( empty($cdata['unfreezetime']) ||
		difftime($time, $cdata['unfreezetime'])<=0 ) ) return TRUE;
	return FALSE;
}

function checkargs($args, $mandatory)
{
	global $api;

	foreach ( $mandatory as $arg ) {
		if ( !isset($args[$arg]) ) {
			$api->createError("argument '$arg' is mandatory");
		}
	}
}

function safe_int($value)
{
	return is_null($value) ? null : (int)$value;
}

function safe_float($value, $decimals = null)
{
	if ( is_null($value) ) return null;
	if ( is_null($decimals) ) return (float)$value;

	// Truncate the string version to a specified number of decimals,
	// since PHP floats seem not very reliable in not giving e.g.
	// 1.9999 instead of 2.0.
	$decpos = strpos((string)$value, '.');
	if ( $decpos===FALSE ) return (float)$value;
	return (float)substr((string)$value, 0, $decpos+$decimals+1);
}

function safe_bool($value)
{
	return is_null($value) ? null : (bool)$value;
}

function safe_string($value)
{
	return is_null($value) ? null : (string)$value;
}

function give_back_judging($judgingid, $submitid) {
	global $DB;

	$DB->q('UPDATE judging SET valid = 0, rejudgingid = NULL WHERE judgingid = %i',
	       $judgingid);
	$DB->q('UPDATE submission SET judgehost = NULL
		WHERE submitid = %i', $submitid);
}

$api = new RestApi();

/**
 * API information
 */
function info()
{
	return array('api_version' => DOMJUDGE_API_VERSION,
	             'domjudge_version' => DOMJUDGE_VERSION);
}
$doc = "Get general API information.";
$api->provideFunction('GET', 'info', $doc);

// helper function to convert the data in the cdata object to the specified values
function cdataHelper($cdata)
{
	// TODO: clarify formal_name, its use and origin
	return array(
		'id'                         => safe_int($cdata['cid']),
		'shortname'                  => $cdata['shortname'],
		'name'                       => $cdata['name'],
		'formal_name'                => $cdata['name'],
		'start_time'                 => Utils::absTime($cdata['starttime']),
		'end_time'                   => Utils::absTime($cdata['endtime']),
		'duration'                   => Utils::relTime($cdata['endtime'] - $cdata['starttime']),
		'scoreboard_freeze_duration' => Utils::relTime($cdata['endtime'] - $cdata['freezetime']),
		'unfreeze'                   => Utils::absTime($cdata['unfreezetime']),
		'penalty'                    => safe_int(dbconfig_get('penalty_time', 20)),
	);
}

/**
 * Contest information
 */
function contest()
{
	global $cids, $cdatas, $userdata;

	if ( checkrole('jury') ) {
		$cdatas = getCurContests(TRUE);
	} elseif ( isset($userdata['teamid']) ) {
		$cdatas = getCurContests(TRUE, $userdata['teamid']);
	}

	if (empty($cdatas)) {
		return null;
	}

	$cid = $cids[0];
	$cdata = $cdatas[$cid];
	return cdataHelper($cdata);
}
$doc = "Get information about the current contest: id, shortname, name, start_time, end_time, duration, scoreboard_freeze_duration, unfreeze, and penalty. ";
$doc .= "If more than one contest is active, return information about the first one.";
$api->provideFunction('GET', 'contest', $doc);


/**
 * Contests information
 */
function contests()
{
	global $cdatas, $userdata;

	if ( checkrole('jury') ) {
		$cdatas = getCurContests(TRUE);
	} elseif ( isset($userdata['teamid']) ) {
		$cdatas = getCurContests(TRUE, $userdata['teamid']);
	}

	return array_map("cdataHelper", $cdatas);
}
$doc = "Get information about all current contests: id, shortname, name, start_time, end_time, duration, scoreboard_freeze_duration, unfreeze, and penalty. ";
$api->provideFunction('GET', 'contests', $doc);

/**
 * Get information about the current user
 */
function user()
{
	global $userdata;

	$return = array(
		'id'       => safe_int($userdata['userid']),
		'teamid'   => safe_int($userdata['teamid']),
		'email'    => $userdata['email'],
		'ip'       => $userdata['ip_address'],
		'lastip'   => $userdata['last_ip_address'],
		'name'     => $userdata['name'],
		'username' => $userdata['username'],
		'roles'    => $userdata['roles'],
	);
	return $return;
}
$doc = "Get information about the currently logged in user. If no user is logged in, will return null for all values.";
$api->provideFunction('GET', 'user', $doc);

/**
 * Problems information
 */
function problems($args)
{
	global $DB, $cdatas, $userdata, $cids;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['probid']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?probid={id}");
		}
		$args['probid'] = $args['__primary_key'];
	}

	if ( isset($args['cid']) ) {
		$cid = safe_int($args['cid']);
	} else {
		if ( count($cids)==1 ) {
			$cid = reset($cids);
		} else {
			$api->createError("No contest ID specified but active contest is ambiguous.");
		}
	}

	// Check that user has access to the problems in this contest:
	if ( checkrole('team') ) $cdatas = getCurContests(TRUE, $userdata['teamid']);
	if ( checkrole('jury') ||
	     (isset($cdatas[$cid]) && difftime(now(), $cdatas[$cid]['starttime'])>=0) ) {

		$query = 'TABLE SELECT probid AS id, shortname AS label, shortname, name, color,
		                  COUNT(testcaseid) AS test_data_count
		                  FROM problem
		                  INNER JOIN contestproblem USING (probid)
				  JOIN testcase USING (probid)
				  WHERE cid = %i AND allow_submit = 1';

		$byProbId = array_key_exists('probid', $args);
		$query .= ($byProbId ? ' AND probid = %i' : ' %_');
		$probid = ($byProbId ? $args['probid'] : 0);

		$pdatas = $DB->q($query . ' GROUP BY probid ORDER BY shortname', $cid, $probid);
	} else {
		$pdatas = array();
	}

	$ordinal = 0;
	foreach ( $pdatas as $key => $pdata ) {
		if ( !isset($pdata['color']) ) {
			$pdatas[$key]['rgb'] = null;
		} elseif ( preg_match('/^#[[:xdigit:]]{3,6}$/',$pdata['color']) ) {
			$pdatas[$key]['rgb'] = $pdata['color'];
			$pdatas[$key]['color'] = hex_to_color($pdata['color']);
		} else {
			$pdatas[$key]['rgb'] = color_to_hex($pdata['color']);
		}
		// We sort above table by shortname, i.e in the same way we
		// sort the problems in the scoreboard.
		$pdatas[$key]['ordinal'] = $ordinal++;
	}

	$is_jury = checkrole('jury');
	return array_map(function($pdata) use ($is_jury) {
		$ret = array(
			'id'         => safe_int($pdata['id']),
			'label'      => $pdata['label'],
			'short_name' => $pdata['shortname'],
			'name'       => $pdata['name'],
			'rgb'        => $pdata['rgb'],
			'color'      => $pdata['color'],
			'ordinal'    => safe_int($pdata['ordinal']),
		);
		if ( $is_jury ) {
			$ret['test_data_count'] = $pdata['test_data_count'];
		}
		return $ret;
	}, $pdatas);
}
$doc = "Get a list of problems in a contest, with for each problem: id, shortname, name and colour.";
$args = array('cid' => 'Contest ID.', 'probid' => 'Problem ID.');
$exArgs = array(array('cid' => 2));
$api->provideFunction('GET', 'problems', $doc, $args, $exArgs);

/**
 * Judgings information
 */
function judgings($args)
{
	global $DB, $userdata, $cdatas, $VERDICTS;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['judging_id']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?judging_id={id}");
		}
		$args['judging_id'] = $args['__primary_key'];
	}

	$query = 'SELECT j.judgingid, j.cid, j.submitid, j.result, j.starttime, j.endtime
	          FROM judging j
	          LEFT JOIN contest c USING (cid)
	          LEFT JOIN submission s USING (submitid)
	          WHERE s.submittime < c.endtime';

	$result = 0;
	if ( array_key_exists('result', $args) ) {
		$query .= ' AND result = %s';
		$result = $args['result'];
	} else {
		$query .= ' %_';
		if ( !(checkrole('admin') || checkrole('judgehost')) ) {
			$query .= ' AND result IS NOT NULL';
		}
	}

	if ( ! checkrole('jury') ) { // This implies we must be a team
		$query .= ' AND teamid = %i';
		$teamid = $userdata['teamid'];
	} else {
		$query .= ' %_';
		$teamid = 0;
	}

	$hasCid = array_key_exists('cid', $args);
	$query .= ($hasCid ? ' AND cid = %i' : ' %_');
	$cid = ($hasCid ? $args['cid'] : 0);

	$hasJudgingid = array_key_exists('judging_id', $args);
	$query .= ($hasJudgingid ? ' AND judgingid = %i' : ' %_');
	$judgingid = ($hasJudgingid ? $args['judging_id'] : 0);

	$hasSubmitid = array_key_exists('submission_id', $args);
	$query .= ($hasSubmitid ? ' AND submitid = %i' : ' %_');
	$submitid = ($hasSubmitid ? $args['submission_id'] : 0);

	$query .= ' ORDER BY judgingid';

	$q = $DB->q($query, $result, $teamid, $cid, $judgingid, $submitid);

	$res = array();
	while ( $row = $q->next() ) {

		$res[] = array(
			'id'                 => safe_int($row['judgingid']),
			'submission_id'      => safe_int($row['submitid']),
			'judgement_type_id'  => $VERDICTS[$row['result']],
			'start_time'         => Utils::absTime($row['starttime']),
			'start_contest_time' => Utils::relTime($row['starttime'] - $cdatas[$row['cid']]['starttime']),
			'end_time'           => Utils::absTime($row['endtime']),
			'end_contest_time'   => Utils::relTime($row['endtime'] - $cdatas[$row['cid']]['starttime']),
		);
	}
	return $res;
}
$doc = 'Get all or selected judgings. This includes those post-freeze, so currently limited to jury, or as a team but then restricted your own submissions.';
$args = array('cid' => 'Contest ID. If not provided, get judgings of all active contests',
              'result' => 'Search only for judgings with a certain result',
              'judging_id' => 'Search only for a certain ID',
              'submission_id' => 'Search only for judgings associated to this submission ID');
$exArgs = array(array('cid' => 2), array('result' => 'correct'), array('first_id' => 800, 'limit' => 10));
$roles = array('jury','team','judgehost');
$api->provideFunction('GET', 'judgings', $doc, $args, $exArgs, $roles);

function judgements($args)
{
	return judgings($args);
}
$api->provideFunction('GET', 'judgements', $doc, $args, $exArgs, $roles);

function judgings_POST($args)
{
	global $DB, $api;

	checkargs($args, array('judgehost'));

	$host = $args['judgehost'];
	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s', now(), $host);

	// If this judgehost is not active, there's nothing to do
	$active = $DB->q('MAYBEVALUE SELECT active FROM judgehost WHERE hostname = %s', $host);
	if ( !$active ) return '';

	$cdatas = getCurContests(TRUE);
	$cids = array_keys($cdatas);

	if ( empty($cids) ) return '';

	// Get judgehost restrictions
	$contests = array();
	$problems = array();
	$languages = array();
	$restrictions = $DB->q('MAYBEVALUE SELECT restrictions FROM judgehost
	                        INNER JOIN judgehost_restriction USING (restrictionid)
	                        WHERE hostname = %s', $host);
	if ( $restrictions ) {
		$restrictions = json_decode($restrictions, true);
		$contests = @$restrictions['contest'];
		$problems = @$restrictions['problem'];
		$languages = @$restrictions['language'];
		$rejudge_own = @$restrictions['rejudge_own'];
	}

	$extra_join = '';
	$extra_where = '';
	if ( empty($contests) ) {
		$extra_where .= '%_ ';
	} else {
		$extra_where .= 'AND s.cid IN (%Ai) ';
	}

	if ( empty($problems) ) {
		$extra_where .= '%_ ';
	} else {
		$extra_join  .= 'LEFT JOIN problem p USING (probid) ';
		$extra_where .= 'AND s.probid IN (%Ai) ';
	}

	if ( empty($languages) ) {
		$extra_where .= '%_ ';
	} else {
		$extra_where .= 'AND s.langid IN (%As) ';
	}

	if ( isset($rejudge_own) && (bool)$rejudge_own==false ) {
		$extra_join  .= 'LEFT JOIN judging j ON (j.submitid=s.submitid AND j.judgehost=%s) ';
		$extra_where .= 'AND j.judgehost IS NULL ';
	} else {
		$extra_join  .= '%_ ';
	}


	// Prioritize teams according to last judging time
	$submitid = $DB->q('MAYBEVALUE SELECT s.submitid
	                    FROM submission s
	                    LEFT JOIN team t USING (teamid)
	                    LEFT JOIN language l USING (langid)
	                    LEFT JOIN contestproblem cp USING (probid, cid) ' .
	                   $extra_join .
	                   'WHERE s.judgehost IS NULL AND s.cid IN (%Ai)
	                    AND l.allow_judge = 1 AND cp.allow_judge = 1 AND s.valid = 1 ' .
	                   $extra_where .
	                   'ORDER BY judging_last_started ASC, submittime ASC, s.submitid ASC
	                    LIMIT 1',
	                   $host, $cids, $contests, $problems, $languages);

	if ( $submitid ) {
		// update exactly one submission with our judgehost name
		// Note: this might still return 0 if another judgehost beat
		// us to it
		$numupd = $DB->q('RETURNAFFECTED UPDATE submission
		                  SET judgehost = %s
		                  WHERE submitid = %i AND judgehost IS NULL',
		                  $host, $submitid);

		// TODO: a small optimisation could be made: if numupd=0 but
		// numopen > 1; not return but retry procudure again immediately
	}

	if ( empty($submitid) || $numupd == 0 ) return '';

	$row = $DB->q('TUPLE SELECT s.submitid, s.cid, s.teamid, s.probid, s.langid, s.rejudgingid, s.entry_point,
	               time_factor*timelimit AS maxruntime,
	               p.memlimit, p.outputlimit,
	               special_run AS run, special_compare AS compare,
	               special_compare_args AS compare_args, compile_script
	               FROM submission s
	               LEFT JOIN problem p USING (probid)
	               LEFT JOIN language l USING (langid)
	               WHERE submitid = %i', $submitid);

	$DB->q('UPDATE team SET judging_last_started = %s WHERE teamid = %i',
	       now(), $row['teamid']);

	if ( empty($row['memlimit']) ) {
		$row['memlimit'] = dbconfig_get('memory_limit');
	}
	if ( empty($row['outputlimit']) ) {
		$row['outputlimit'] = dbconfig_get('output_limit');
	}
	if ( empty($row['compare']) ) {
		$row['compare'] = dbconfig_get('default_compare');
	}
	if ( empty($row['run']) ) {
		$row['run'] = dbconfig_get('default_run');
	}

	$compare_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
	                          WHERE execid = %s', $row['compare']);
	$row['compare_md5sum'] = $compare_md5sum;
	$run_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
	                      WHERE execid = %s', $row['run']);
	$row['run_md5sum'] = $run_md5sum;
	if ( !empty($row['compile_script']) ) {
		$compile_script_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
		                                 WHERE execid = %s', $row['compile_script']);
		$row['compile_script_md5sum'] = $compile_script_md5sum;
	}

	$is_rejudge = isset($row['rejudgingid']);
	if ( $is_rejudge ) {
		// FIXME: what happens if there is no valid judging?
		$prev_rejudgingid = $DB->q('MAYBEVALUE SELECT judgingid
		                            FROM judging
		                            WHERE submitid=%i AND valid=1',
		                           $submitid);
	}
	$jid = $DB->q('RETURNID INSERT INTO judging (submitid,cid,starttime,judgehost' .
	              ($is_rejudge ? ', rejudgingid, prevjudgingid, valid' : '' ) .
	              ') VALUES(%i,%i,%s,%s' . ($is_rejudge ? ',%i,%i,%i' : '%_ %_ %_') .
	              ')', $submitid, $row['cid'], now(), $host,
	              @$row['rejudgingid'], @$prev_rejudgingid, !$is_rejudge);

	eventlog('judging', $jid, 'create', $row['cid']);

	$row['submitid']    = safe_int($row['submitid']);
	$row['cid']         = safe_int($row['cid']);
	$row['teamid']      = safe_int($row['teamid']);
	$row['probid']      = safe_int($row['probid']);
	$row['langid']      = $row['langid'];
	$row['rejudgingid'] = safe_int($row['rejudgingid']);
	$row['maxruntime']  = safe_float($row['maxruntime'],6);
	$row['memlimit']    = safe_int($row['memlimit']);
	$row['outputlimit'] = safe_int($row['outputlimit']);
	$row['judgingid']   = safe_int($jid);

	return $row;
}
$doc = 'Request a new judging to be judged.';
$args = array('judgehost' => 'Judging is to be judged by this specific judgehost.');
$exArgs = array();
$roles = array('judgehost');
$api->provideFunction('POST', 'judgings', $doc, $args, $exArgs, $roles);

function judgings_PUT($args)
{
	global $DB, $api;

	if ( !isset($args['__primary_key']) ) {
		$api->createError("judgingid is mandatory");
	}
	$judgingid = $args['__primary_key'];
	if ( !isset($args['judgehost']) ) {
		$api->createError("judgehost is mandatory");
	}

	if ( isset($args['output_compile']) ) {
		if ( $args['compile_success'] ) {
			$DB->q('UPDATE judging SET output_compile = %s
			        WHERE judgingid = %i AND judgehost = %s',
			       base64_decode($args['output_compile']),
			       $judgingid, $args['judgehost']);
		} else {
			$DB->q('UPDATE judging SET output_compile = %s,
			        result = "compiler-error", endtime=%s
			        WHERE judgingid = %i AND judgehost = %s',
			       base64_decode($args['output_compile']), now(),
			       $judgingid, $args['judgehost']);
			$cid = $DB->q('VALUE SELECT s.cid FROM judging
			               LEFT JOIN submission s USING(submitid)
			               WHERE judgingid = %i', $judgingid);
			auditlog('judging', $judgingid, 'judged', 'compiler-error',
			         $args['judgehost'], $cid);

			$row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid, s.langid, s.submitid
			               FROM judging
			               LEFT JOIN submission s USING(submitid)
			               WHERE judgingid = %i',$judgingid);
			calcScoreRow($row['cid'], $row['teamid'], $row['probid']);

			// We call alert here for the failed submission. Note that
			// this means that these alert messages should be treated
			// as confidential information.
			alert('reject', "submission $row[submitid], judging $judgingid: compiler-error");

			// log to event table if no verification required
			// (case of verification required is handled in www/jury/verify.php)
			if ( ! dbconfig_get('verification_required', 0) ) {
				eventlog('judging', $judgingid, 'update', $row['cid']);
			}
		}
	}

	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s',
	       now(), $args['judgehost']);

	return '';
}
$doc = 'Update a judging.';
$args = array('judgingid' => 'Judging corresponds to this specific judgingid.',
	'judgehost' => 'Judging is judged by this specific judgehost.',
	'compile_success' => 'Did the compilation succeed?',
	'output_compile' => 'Ouput of compilation phase (base64 encoded).');
$exArgs = array();
$roles = array('judgehost');
$api->provideFunction('PUT', 'judgings', $doc, $args, $exArgs, $roles);

/**
 * Judging_Runs
 */
function judging_runs_POST($args)
{
	global $DB, $api;

	checkargs($args, array('judgingid', 'testcaseid', 'runresult', 'runtime',
	                       'output_run', 'output_diff', 'output_error', 'output_system', 'judgehost'));

	$results_remap = dbconfig_get('results_remap');
	$results_prio = dbconfig_get('results_prio');

	if ( array_key_exists($args['runresult'], $results_remap) ) {
		logmsg(LOG_INFO, "Testcase $args[testcaseid] remapping result " . $args['runresult'] .
		                 " -> " . $results_remap[$args['runresult']]);
		$args['runresult'] = $results_remap[$args['runresult']];
	}

	$jud = $DB->q('TUPLE SELECT judgingid, cid, result FROM judging
	               WHERE judgingid = %i', $args['judgingid']);

	$runid = $DB->q('RETURNID INSERT INTO judging_run (judgingid, testcaseid, runresult,
	                 runtime, endtime, output_run, output_diff, output_error, output_system)
	                 VALUES (%i, %i, %s, %f, %s, %s, %s, %s, %s)',
	                $args['judgingid'], $args['testcaseid'],
	                $args['runresult'], $args['runtime'], now(),
	                base64_decode($args['output_run']),
	                base64_decode($args['output_diff']),
	                base64_decode($args['output_error']),
	                base64_decode($args['output_system']));

	eventlog('judging_run', $runid, 'create', $jud['cid']);

	// result of this judging_run has been stored. now check whether
	// we're done or if more testcases need to be judged.

	$probid = $DB->q('VALUE SELECT probid FROM testcase
	                  WHERE testcaseid = %i', $args['testcaseid']);

	$runresults = $DB->q('COLUMN SELECT runresult
	                      FROM judging_run LEFT JOIN testcase USING(testcaseid)
	                      WHERE judgingid = %i ORDER BY rank', $args['judgingid']);
	$numtestcases = $DB->q('VALUE SELECT count(*) FROM testcase WHERE probid = %i', $probid);

	$allresults = array_pad($runresults, $numtestcases, null);

	if ( ($result = getFinalResult($allresults, $results_prio))!==NULL ) {

		// Lookup global lazy evaluation of results setting and
		// possible problem specific override.
		$lazy_eval = dbconfig_get('lazy_eval_results', true);
		$prob_lazy = $DB->q('MAYBEVALUE SELECT cp.lazy_eval_results
		                     FROM judging j
		                     LEFT JOIN submission s USING(submitid)
		                     LEFT JOIN contestproblem cp ON (cp.cid=j.cid AND cp.probid=s.probid)
		                     WHERE judgingid = %i', $args['judgingid']);
		if ( isset($prob_lazy) ) $lazy_eval = (bool)$prob_lazy;

		if ( count($runresults) == $numtestcases || $lazy_eval ) {
			// NOTE: setting endtime here determines in testcases_GET
			// whether a next testcase will be handed out.
			$DB->q('UPDATE judging SET result = %s, endtime = %s
			        WHERE judgingid = %i', $result, now(), $args['judgingid']);
		} else {
			$DB->q('UPDATE judging SET result = %s
			        WHERE judgingid = %i', $result, $args['judgingid']);
		}

		// Only update if the current result is different from what we
		// had before. This should only happen when the old result was
		// NULL.
		if ( $jud['result'] !== $result ) {
			if ( $jud['result'] !== NULL ) {
				error('internal bug: the evaluated result changed during judging');
			}

			$row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid, s.langid, s.submitid
			               FROM judging
			               LEFT JOIN submission s USING(submitid)
			               WHERE judgingid = %i',$args['judgingid']);
			calcScoreRow($row['cid'], $row['teamid'], $row['probid']);

			// We call alert here before possible validation. Note
			// that this means that these alert messages should be
			// treated as confidential information.
			alert(($result==='correct' ? 'accept' : 'reject'),
			      "submission $row[submitid], judging $args[judgingid]: $result");

			// log to event table if no verification required
			// (case of verification required is handled in www/jury/verify.php)
			if ( ! dbconfig_get('verification_required', 0) ) {
				eventlog('judging', $args['judgingid'], 'update', $row['cid']);
				if ( $result == 'correct' ) {
					// prevent duplicate balloons in case of multiple correct submissions
					$numcorrect = $DB->q('VALUE SELECT count(submitid)
					                      FROM balloon
					                      LEFT JOIN submission USING(submitid)
					                      WHERE valid = 1 AND probid = %i
					                      AND teamid = %i AND cid = %i',
					                     $row['probid'], $row['teamid'], $row['cid']);
					if ( $numcorrect == 0 ) {
						$balloons_enabled = (bool)$DB->q("VALUE SELECT process_balloons
						                                  FROM contest WHERE cid = %i",
						                                 $row['cid']);
						if ( $balloons_enabled ) {
							$DB->q('INSERT INTO balloon (submitid) VALUES(%i)',
							       $row['submitid']);
						}
					}
				}
			}

			auditlog('judging', $args['judgingid'], 'judged', $result, $args['judgehost']);
		}
	}

	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s',
	       now(), $args['judgehost']);

	return '';
}
$doc = 'Add a new judging_run to the list of judging_runs. When relevant, finalize the judging.';
$args = array('judgingid' => 'Judging_run corresponds to this specific judgingid.',
	'testcaseid' => 'Judging_run corresponding to this specific testcaseid.',
	'runresult' => 'Result of this run.',
	'runtime' => 'Runtime of this run.',
	'output_run' => 'Program output of this run (base64 encoded).',
	'output_diff' => 'Program diff of this run (base64 encoded).',
	'output_error' => 'Program error output of this run (base64 encoded).',
	'output_system' => 'Judging system output of this run (base64 encoded).',
	'judgehost' => 'Judgehost performing this judging');
$exArgs = array();
$roles = array('judgehost');
$api->provideFunction('POST', 'judging_runs', $doc, $args, $exArgs, $roles);

/**
 * DB configuration
 */
function config($args)
{
	if ( isset($args['name']) ) {
		return array($args['name'] => dbconfig_get($args['name'], null, false));
	}

	return dbconfig_get(null, null, false);
}
$doc = 'Get configuration variables.';
$args = array('name' => 'Search only a single config variable.');
$exArgs = array(array('name' => 'sourcesize_limit'));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'config', $doc, $args, $exArgs);

/**
 * Submissions information
 */
function submissions($args)
{
	global $DB, $cdatas, $api;

	$query = 'SELECT submitid, teamid, probid, langid, submittime, cid, entry_point
	          FROM submission WHERE valid=1';

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['id']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?id={id}");
		}
		$args['id'] = $args['__primary_key'];
	}

	$hasCid = array_key_exists('cid', $args);
	$query .= ($hasCid ? ' AND cid = %i' : ' %_');
	$cid = ($hasCid ? $args['cid'] : 0);

	$hasLanguage = array_key_exists('language_id', $args);
	$query .= ($hasLanguage ? ' AND langid = %s' : ' %_');
	$languageId = ($hasLanguage ? $args['language_id'] : 0);

	$hasSubmitid = array_key_exists('id', $args);
	$query .= ($hasSubmitid ? ' AND submitid = %i' : ' %_');
	$submitid = ($hasSubmitid ? $args['id'] : 0);

	if ( !$hasSubmitid && $cid == 0 && !checkrole('jury') ) {
		$api->createError("argument 'id' or 'cid' is mandatory for non-jury users");
	}

	$teamid = 0;
	$freezetime = 0;
	if ( $cid != 0 && infreeze($cdatas[$cid], now()) && !checkrole('jury') ) {
		$query .= ' AND ( submittime <= %i';
		$freezetime = $cdatas[$cid]['freezetime'];
		if ( checkrole('team') ) {
			$query .= ' OR teamid = %i';
			$teamid = $userdata['teamid'];
		} else {
			$query .= ' %_';
		}
		$query .= ' )';
	} else {
		$query .= ' %_ %_';
	}

	$query .= ' ORDER BY submitid';

	$q = $DB->q($query, $cid, $languageId, $submitid, $freezetime, $teamid);
	$res = array();
	while ( $row = $q->next() ) {
		$res[] = array(
			'id'           => safe_int($row['submitid']),
			'label'        => safe_int($row['submitid']),
			'team_id'      => safe_int($row['teamid']),
			'problem_id'   => safe_int($row['probid']),
			'language_id'  => $row['langid'],
			'time'         => Utils::absTime($row['submittime']),
			'contest_time' => Utils::relTime($row['submittime'] - $cdatas[$row['cid']]['starttime']),
			'contest_id'   => safe_int($row['cid']),
			'entry_point'  => $row['entry_point'],
			);
	}
	return $res;
}
$args = array('cid' => 'Contest ID. If not provided, get submissions of all active contests',
              'language_id' => 'Search only for submissions in a certain language.',
              'id' => 'Search only a certain ID');
$doc = 'Get a list of all valid submissions.';
$exArgs = array(array('id' => 42), array('language_id' => 'cpp'));
$api->provideFunction('GET', 'submissions', $doc, $args, $exArgs);

/**
 * POST a new submission
 */
function submissions_POST($args)
{
	global $userdata, $DB, $api;
	checkargs($args, array('shortname','langid'));
	checkargs($userdata, array('teamid'));
	$contests = getCurContests(TRUE, $userdata['teamid'], false, 'shortname');
	$contest_shortname = null;

	if ( isset($args['contest']) ) {
		if ( isset($contests[$args['contest']]) ) {
			$contest_shortname = $args['contest'];
		} else {
			$api->createError("Cannot find active contest '$args[contest]', or you are not part of it.");
		}
	} else {
		if ( count($contests) == 1 ) {
			$contest_shortname = key($contests);
		} else {
			$api->createError("No contest specified while multiple active contests found.");
		}
	}
	$cid = $contests[$contest_shortname]['cid'];

	$probid = $DB->q('MAYBEVALUE SELECT probid FROM problem
	                  INNER JOIN contestproblem USING (probid)
	                  WHERE shortname = %s AND cid = %i AND allow_submit = 1',
	                 $args['shortname'], $cid);
	if ( empty($probid ) ) {
		error("Problem " . $args['shortname'] . " not found or or not submittable");
	}

	// rebuild array of filenames, paths to get rid of empty upload fields
	$FILEPATHS = $FILENAMES = array();
	foreach($_FILES['code']['tmp_name'] as $fileid => $tmpname ) {
		if ( !empty($tmpname) ) {
			checkFileUpload($_FILES['code']['error'][$fileid]);
			$FILEPATHS[] = $_FILES['code']['tmp_name'][$fileid];
			$FILENAMES[] = $_FILES['code']['name'][$fileid];
		}
	}

	$entry_point = isset($args['entry_point']) ? $args['entry_point'] : NULL;
	$sid = submit_solution($userdata['teamid'], $probid, $cid, $args['langid'], $FILEPATHS, $FILENAMES, NULL, $entry_point);

	auditlog('submission', $sid, 'added', 'via api', null, $cid);

	return safe_int($sid);
}

$args = array('code[]' => 'Array of source files to submit',
              'shortname' => 'Problem shortname',
              'langid' => 'Language ID',
	      'contest' => 'Contest short name. Required if more than one contest is active',
	      'entry_point' => 'Optional entry point, e.g. Java main class.',
);
$doc = 'Post a new submission. You need to be authenticated with a team role. Returns the submission id. This is used by the submit client.

A trivial command line submisson using the curl binary could look like this:

curl -n -F "shortname=hello" -F "langid=c" -F "cid=2" -F "code[]=@test1.c" -F "code[]=@test2.c"  http://localhost/domjudge/api/submissions';
$exArgs = array();
$roles = array('team');
$api->provideFunction('POST', 'submissions', $doc, $args, $exArgs, $roles);

/**
 * Submission Files
 */
function submission_files($args)
{
	global $DB, $api;

	checkargs($args, array('submission_id'));

	$sources = $DB->q('SELECT submitfileid, submitid, filename, sourcecode FROM submission_file
	                   WHERE submitid = %i ORDER BY rank', $args['submission_id']);

	if ( $sources->count()==0 ) {
		$api->createError("Cannot find source files for submission '$args[id]'.");
	}

	$ret = array();
	while($src = $sources->next()) {
		$ret[] = array(
			'id'            => $src['submitfileid'],
			'submission_id' => $src['submitid'],
			'filename'      => $src['filename'],
			'source'        => base64_encode($src['sourcecode']),
		);
	}

	return $ret;
}
$args = array('submission_id' => 'Get only the corresponding submission files.');
$doc = 'Get a list of all submission files. The file contents will be base64 encoded.';
$exArgs = array(array('submission_id' => 3));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'submission_files', $doc, $args, $exArgs, $roles);

/**
 * Testcases
 */
function testcases($args)
{
	global $DB, $api;

	checkargs($args, array('judgingid'));

	// endtime is set: judging is fully done; return empty
	$row = $DB->q('TUPLE SELECT endtime,probid
	               FROM judging LEFT JOIN submission USING(submitid)
	               WHERE judgingid = %i', $args['judgingid']);
	if ( !empty($row['endtime']) ) return '';

	$judging_runs = $DB->q("COLUMN SELECT testcaseid FROM judging_run
	                        WHERE judgingid = %i", $args['judgingid']);
	$sqlextra = count($judging_runs) ? "AND testcaseid NOT IN (%Ai)" : "%_";
	$testcase = $DB->q("MAYBETUPLE SELECT testcaseid, rank, probid, md5sum_input, md5sum_output
	                    FROM testcase WHERE probid = %i $sqlextra ORDER BY rank LIMIT 1",
	                   $row['probid'], $judging_runs);

	// would probably never be empty, because then endtime would also
	// have been set. we cope with it anyway for now.
	if (is_null($testcase)) {
		return null;
	}

	$testcase['testcaseid'] = safe_int($testcase['testcaseid']);
	$testcase['rank'] = safe_int($testcase['rank']);
	$testcase['probid'] = safe_int($testcase['probid']);

	return $testcase;
}
$args = array('judgingid' => 'Get the next-to-judge testcase for this judging.');
$doc = 'Get a testcase.';
$exArgs = array();
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'testcases', $doc, $args, $exArgs, $roles);

function testcase_files($args)
{
	global $DB, $api;

	checkargs($args, array('testcaseid'));

	if ( !isset($args['input']) && !isset($args['output']) ) {
		$api->createError("either input or output is mandatory");
	}
	if ( isset($args['input']) && isset($args['output']) ) {
		$api->createError("cannot select both input and output");
	}
	$inout = 'output';
	if ( isset($args['input']) ) {
		$inout = 'input';
	}

	$content = $DB->q("MAYBEVALUE SELECT SQL_NO_CACHE $inout FROM testcase
	                   WHERE testcaseid = %i", $args['testcaseid']);

	if ( is_null($content) ) {
		$api->createError("Cannot find testcase '$args[testcaseid]'.");
	}

	return base64_encode($content);
}
$args = array('testcaseid' => 'Get only the corresponding testcase.',
	'input' => 'Get the input file.',
	'output' => 'Get the output file.');
$doc = 'Get a testcase file, base64 encoded.';
$exArgs = array(array('testcaseid' => '3', 'input' => TRUE));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'testcase_files', $doc, $args, $exArgs, $roles);

// executable zip, e.g. for compare scripts
function executable($args)
{
	global $DB, $api;

	checkargs($args, array('execid'));

	$content = $DB->q("MAYBEVALUE SELECT SQL_NO_CACHE zipfile FROM executable
	                   WHERE execid = %s", $args['execid']);

	if ( is_null($content) ) {
		$api->createError("Cannot find executable '$args[execid]'.");
	}

	return base64_encode($content);
}
$args = array('execid' => 'Get only the corresponding executable.');
$doc = 'Get an executable zip file, base64 encoded.';
$exArgs = array(array('execid' => 'ignorews'));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'executable', $doc, $args, $exArgs, $roles);

/**
 * Judging Queue
 *
 * FIXME: duplicates code with judgings_post
 * not used in judgedaemon
 */
function queue($args)
{
	global $DB;

	// TODO: make this configurable
	$cdatas = getCurContests(TRUE);
	$cids = array_keys($cdatas);

	if ( empty($cids) ) {
		return array();
	}

	$hasLimit = array_key_exists('limit', $args);
	// TODO: validate limit

	$sdatas = $DB->q('TABLE SELECT submitid
	                  FROM submission s
	                  LEFT JOIN team t USING (teamid)
	                  LEFT JOIN problem p USING (probid)
	                  LEFT JOIN language l USING (langid)
	                  LEFT JOIN contestproblem cp USING (probid, cid)
	                  WHERE judgehost IS NULL AND s.cid IN (%Ai)
	                  AND l.allow_judge = 1 AND cp.allow_judge = 1 AND valid = 1
	                  ORDER BY judging_last_started ASC, submittime ASC, submitid ASC' .
	                 ($hasLimit ? ' LIMIT %i' : ' %_'),
	                 $cids, ($hasLimit ? $args['limit'] : -1));

	return array_map(function($sdata) {
		return array('submitid' => safe_int($sdata['submitid']));
	}, $sdatas);
}
$args = array('limit' => 'Get only the first N queued submissions');
$doc = 'Get a list of all queued submission ids.';
$exArgs = array(array('limit' => 10));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'queue', $doc, $args, $exArgs, $roles);

/**
 * Judging runs information
 */
function runs($args)
{
	global $DB, $cdatas, $VERDICTS;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['run_id']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?run_id={id}");
		}
		$args['run_id'] = $args['__primary_key'];
	}

	$query = 'TABLE SELECT runid, judgingid, runresult, rank, jr.endtime, cid
		  FROM judging_run jr
		  LEFT JOIN testcase USING (testcaseid)
		  LEFT JOIN judging USING (judgingid)
		  WHERE TRUE';

	$hasFirstId = array_key_exists('first_id', $args);
	$query .= ($hasFirstId ? ' AND runid >= %i' : ' AND TRUE %_');
	$firstId = ($hasFirstId ? $args['first_id'] : 0);

	$hasLastId = array_key_exists('last_id', $args);
	$query .= ($hasLastId ? ' AND runid <= %i' : ' AND TRUE %_');
	$lastId = ($hasLastId ? $args['last_id'] : 0);

	$hasJudgingid = array_key_exists('judging_id', $args);
	$query .= ($hasJudgingid ? ' AND judgingid = %i' : ' %_');
	$judgingid = ($hasJudgingid ? $args['judging_id'] : 0);

	$hasRunId = array_key_exists('run_id', $args);
	$query .= ($hasRunId ? ' AND runid = %i' : ' %_');
	$runid = ($hasRunId ? $args['run_id'] : 0);

	$hasLimit = array_key_exists('limit', $args);
	$query .= ($hasLimit ? ' LIMIT %i' : ' %_');
	$limit = ($hasLimit ? $args['limit'] : -1);
	// TODO: validate limit

	$runs = $DB->q($query, $firstId, $lastId, $judgingid, $runid, $limit);
	return array_map(function($run) use ($VERDICTS, $cdatas) {
		return array(
			'id'                => safe_int($run['runid']),
			'judgement_id'      => safe_int($run['judgingid']),
			'ordinal'           => safe_int($run['rank']),
			'judgement_type_id' => $VERDICTS[$run['runresult']],
			'time'              => Utils::absTime($run['endtime']),
			'contest_time'      => Utils::relTime($run['endtime'] - $cdatas[$run['cid']]['starttime']),
		);
	}, $runs);
}
$doc = 'Get all or selected runs.';
$args = array('first_id' => 'Search from a certain ID',
              'last_id' => 'Search up to a certain ID',
              'run_id' => 'Search only for a certain ID',
              'judging_id' => 'Search only for runs associated to this judging ID',
              'limit' => 'Get only the first N runs');
$exArgs = array(array('first_id' => 800, 'limit' => 10));
$roles = array('jury','judgehost');
$api->provideFunction('GET', 'runs', $doc, $args, $exArgs, $roles);


/**
 * Affiliation information
 */
function affiliations($args)
{
	global $DB;

	// Construct query
	$query = 'TABLE SELECT affilid, shortname, name, country FROM team_affiliation WHERE';

	$byCountry = array_key_exists('country', $args);
	$query .= ($byCountry ? ' country = %s' : ' TRUE %_');
	$country = ($byCountry ? $args['country'] : '');

	$query .= ' ORDER BY name';

	// Run query and return result
	$adatas = $DB->q($query, $country);
	return array_map(function($adata) {
		return array(
			'affilid'   => safe_int($adata['affilid']),
			'shortname' => $adata['shortname'],
			'name'      => $adata['name'],
			'country'   => $adata['country'],
		);
	}, $adatas);
}
$doc = 'Get a list of affiliations, with for each affiliation: affilid, shortname, name and country.';
$optArgs = array('country' => 'ISO 3166-1 alpha-3 country code to search for.');
$exArgs = array(array('country' => 'NLD'));
$api->provideFunction('GET', 'affiliations', $doc, $optArgs, $exArgs);

/**
 * Organization information
 */
function organizations($args)
{
	global $DB;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['affilid']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?affilid={id}");
		}
		$args['affilid'] = $args['__primary_key'];
	}

	// Construct query
	$query = 'TABLE SELECT affilid, shortname, name, country FROM team_affiliation WHERE';

	$byCountry = array_key_exists('country', $args);
	$query .= ($byCountry ? ' country = %s' : ' TRUE %_');
	$country = ($byCountry ? $args['country'] : '');

	$byAffilId = array_key_exists('affilid', $args);
	$query .= ($byAffilId ? ' AND affilid = %i' : ' %_');
	$affilid = ($byAffilId ? $args['affilid'] : '');

	$query .= ' ORDER BY name';

	// Run query and return result
	$adatas = $DB->q($query, $country, $affilid);
	return array_map(function($adata) {
		return array(
			'id'        => safe_int($adata['affilid']),
			'icpc_id'   => safe_int($adata['affilid']),
			'shortname' => $adata['shortname'],
			'name'      => $adata['name'],
			'country'   => $adata['country'],
		);
	}, $adatas);
}
$doc = 'Get a list of affiliations, with for each affiliation: affilid, shortname, name and country.';
$optArgs = array('country' => 'ISO 3166-1 alpha-3 country code to search for.');
$exArgs = array(array('country' => 'NLD'));
$api->provideFunction('GET', 'organizations', $doc, $optArgs, $exArgs);

/**
 * Team information
 */
function teams($args)
{
	global $DB;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['teamid']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?teamid={id}");
		}
		$args['teamid'] = $args['__primary_key'];
	}

	// Construct query
	$query = 'TABLE SELECT teamid AS id, t.name, t.members, t.externalid, a.country AS nationality,
	          t.categoryid AS category, c.name AS `group`, a.affilid, a.name AS affiliation
	          FROM team t
	          LEFT JOIN team_affiliation a USING(affilid)
	          LEFT JOIN team_category c USING (categoryid)
	          WHERE t.enabled = 1';

	$byCategory = array_key_exists('category', $args);
	$query .= ($byCategory ? ' AND categoryid = %i' : ' %_');
	$category = ($byCategory ? $args['category'] : 0);

	$byAffil = array_key_exists('affiliation', $args);
	$query .= ($byAffil ? ' AND affilid = %s' : ' %_');
	$affiliation = ($byAffil ? $args['affiliation'] : 0);

	$byTeamid = array_key_exists('teamid', $args);
	$query .= ($byTeamid ? ' AND teamid = %i' : ' %_');
	$teamid = ($byTeamid ? $args['teamid'] : 0);

	$query .= ($args['public'] ? ' AND visible = 1' : '');

	// Run query and return result
	$tdatas = $DB->q($query, $category, $affiliation, $teamid);
	return array_map(function($tdata) {
		return array(
			'id'              => safe_int($tdata['id']),
			'label'           => safe_int($tdata['id']),
			'name'            => $tdata['name'],
			'members'         => $tdata['members'],
			'nationality'     => $tdata['nationality'],
			'group_id'        => safe_int($tdata['category']),
			'group'           => $tdata['group'],
			'affilid'         => safe_int($tdata['affilid']),
			'organization_id' => safe_int($tdata['affilid']),
			'affiliation'     => $tdata['affiliation'],
			'externalid'      => $tdata['externalid'],
			'icpc_id'         => $tdata['externalid'],
		);
	}, $tdatas);
}
$args = array('category' => 'ID of a single category/group to search for.',
              'affiliation' => 'ID of an affiliation to search for.',
              'teamid' => 'Search for a specific team.');
$doc = 'Get a list of teams containing teamid, name, group and affiliation.';
$exArgs = array(array('category' => 1, 'affiliation' => 'UU'));
$api->provideFunction('GET', 'teams', $doc, $args, $exArgs, null, true);

/**
 * Category information
 */
function categories($args)
{
	global $DB;
	$extra = ($args['public'] ? 'WHERE visible = 1' : '');
	$q = $DB->q('SELECT categoryid, name, color, visible, sortorder
	             FROM team_category ' . $extra . ' ORDER BY sortorder');
	$res = array();
	while ( $row = $q->next() ) {
		$res[] = array(
			'categoryid' => safe_int($row['categoryid']),
			'name'       => $row['name'],
			'color'      => $row['color'],
			'sortorder'  => safe_int($row['sortorder']));
	}
	return $res;
}
$doc = 'Get a list of all categories.';
$api->provideFunction('GET', 'categories', $doc, array(), array(), null, true);

/**
 * Groups information.
 */
function groups($args)
{
	global $DB;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['categoryid']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?categoryid={id}");
		}
		$args['categoryid'] = $args['__primary_key'];
	}

	$query = 'SELECT categoryid, name, color, visible, sortorder
		  FROM team_category
		  WHERE TRUE';
	if ( $args['public'] ) {
		$query .= ' AND visible=1';
	}

	$byCatId = array_key_exists('categoryid', $args);
	$query .= ($byCatId ? ' AND categoryid = %i' : ' %_');
	$categoryId = ($byCatId ? $args['categoryid'] : 0);

	$q = $DB->q($query . ' ORDER BY sortorder', $categoryId);
	$res = array();
	while ( $row = $q->next() ) {
		$res[] = array(
			'id'         => safe_int($row['categoryid']),
			'icpc_id'    => safe_int($row['categoryid']),
			'name'       => $row['name'],
			'color'      => $row['color'],
			'sortorder'  => safe_int($row['sortorder']));
	}
	return $res;
}
$doc = 'Get a list of all groups.';
$api->provideFunction('GET', 'groups', $doc, array(), array(), null, true);

/**
 * Language information
 */
function languages($args)
{
	global $DB;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['langid']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?langid={id}");
		}
		$args['langid'] = $args['__primary_key'];
	}

	$query = 'SELECT langid, name, extensions, allow_judge, time_factor
	          FROM language WHERE allow_submit = 1';

	$byLangId = array_key_exists('langid', $args);
	$query .= ($byLangId ? ' AND langid = %s' : ' %_');
	$langid = ($byLangId ? $args['langid'] : '');

	$q = $DB->q($query, $langid);

	$res = array();
	while ( $row = $q->next() ) {
		$res[] = array(
			'id'           => $row['langid'],
			'name'         => $row['name'],
			'extensions'   => json_decode($row['extensions']),
			'allow_judge'  => safe_bool($row['allow_judge']),
			'time_factor'  => safe_float($row['time_factor']),
			);
	}
	return $res;
}
$doc = 'Get a list of all suported programming languages.';
$args = array('langid' => 'Search for a specific language.');
$api->provideFunction('GET', 'languages', $doc, $args);

/**
 * Clarification information
 */
function clarifications($args)
{
	global $cids, $cdatas, $DB;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['clar_id']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?clar_id={id}");
		}
		$args['clar_id'] = $args['__primary_key'];
	}

	if ( empty($cids) ) {
		return array();
	}

	// Find clarifications, maybe later also provide more info for jury
	$query = 'TABLE SELECT clarid, submittime, probid, body, cid, sender, recipient
		  FROM clarification
	          WHERE cid IN (%Ai)';

	$byProblem = array_key_exists('problem', $args);
	$query .= ($byProblem ? ' AND probid = %i' : ' AND TRUE %_');
	$problem = ($byProblem ? $args['problem'] : null);

	$byClarId = array_key_exists('clar_id', $args);
	$query .= ($byClarId ? ' AND clarid = %i' : ' AND TRUE %_');
	$clarId = ($byClarId ? $args['clar_id'] : null);

	$clar_datas = $DB->q($query, $cids, $problem, $clarId);
	return array_map(function($clar_data) use ($cdatas) {
		return array(
			'id'           => safe_int($clar_data['clarid']),
			'time'         => Utils::absTime($clar_data['submittime']),
			'contest_time' => Utils::relTime($clar_data['submittime'] - $cdatas[$clar_data['cid']]['starttime']),
			'problem_id'   => safe_int($clar_data['probid']),
			'from_team_id' => safe_int($clar_data['sender']),
			'to_team_id'   => safe_int($clar_data['recipient']),
			'text'         => $clar_data['body'],
		);
	}, $clar_datas);
}
$doc = 'Get a list of clarifications.';
$args = array('problem' => 'Search for clarifications about a specific problem.');
$exArgs = array(array('problem' => 'H'));
$roles = array('jury');
$api->provideFunction('GET', 'clarifications', $doc, $args, $exArgs, $roles);

/**
 * Judgehosts
 */
function judgehosts($args)
{
	global $DB;

	$query = 'TABLE SELECT hostname, active, polltime FROM judgehost';

	$byHostname = array_key_exists('hostname', $args);
	$query .= ($byHostname ? ' WHERE hostname = %s' : '%_');
	$hostname = ($byHostname ? $args['hostname'] : null);

	$jdatas = $DB->q($query, $hostname);
	return array_map(function($jdata) {
		return array(
			'hostname' => $jdata['hostname'],
			'active'   => safe_bool($jdata['active']),
			'polltime' => safe_float($jdata['polltime'],3),
		);
	}, $jdatas);
}
$doc = 'Get a list of judgehosts.';
$args = array('hostname' => 'Search only for judgehosts with given hostname.');
$exArgs = array(array('hostname' => 'sparehost'));
$roles = array('jury');
$api->provideFunction('GET', 'judgehosts', $doc, $args, $exArgs, $roles);

function judgehosts_POST($args)
{
	global $DB, $api;

	checkargs($args, array('hostname'));

	$DB->q('INSERT IGNORE INTO judgehost (hostname) VALUES(%s)',
	       $args['hostname']);

	// If there are any unfinished judgings in the queue in my name,
	// they will not be finished. Give them back.
	$query = 'TABLE SELECT judgingid, submitid, cid
	          FROM judging j
	          LEFT JOIN rejudging r USING (rejudgingid)
	          WHERE judgehost = %s AND j.endtime IS NULL
	          AND (j.valid = 1 OR r.valid = 1)';
	$res = $DB->q($query, $args['hostname']);
	foreach ( $res as $jud ) {
		give_back_judging($jud['judgingid'], $jud['submitid']);
		auditlog('judging', $jud['judgingid'], 'given back', null, $args['hostname'], $jud['cid']);
	}

	return array_map(function($jud) {
		return array(
			'judgingid' => safe_int($jud['judgingid']),
			'submitid'  => safe_int($jud['submitid']),
			'cid'       => safe_int($jud['cid']),
		);
	}, $res);
}
$doc = 'Add a new judgehost to the list of judgehosts. Also restarts (and returns) unfinished judgings.';
$args = array('hostname' => 'Add this specific judgehost and activate it.');
$exArgs = array(array('hostname' => 'judge007'));
$roles = array('judgehost');
$api->provideFunction('POST', 'judgehosts', $doc, $args, $exArgs, $roles);

function judgehosts_PUT($args)
{
	global $DB, $api;

	if ( !isset($args['__primary_key']) ) {
		$api->createError("hostname is mandatory");
	}
	$hostname = $args['__primary_key'];
	if ( !isset($args['active']) ) {
		$api->createError("active is mandatory");
	}
	$active = $args['active'];
	$DB->q('UPDATE judgehost SET active=%i WHERE hostname=%s', $active, $hostname);

	return judgehosts(array('hostname' => $hostname));
}
$doc = 'Update the configuration of a judgehost.';
$args = array('active' => 'Activate judgehost?');
$exArgs = array();
$roles = array('judgehost');
$api->provideFunction('PUT', 'judgehosts', $doc, $args, $exArgs, $roles);

// Helper function used below:
function cmp_prob_label($a, $b) { return $a['label'] > $b['label']; }

/**
 * Scoreboard
 */
function scoreboard($args)
{
	global $DB, $api, $cdatas, $cids;

	if ( isset($userdata['teamid']) ) {
		$cdatas = getCurContests(TRUE, $userdata['teamid']);
	}

	if ( isset($args['cid']) ) {
		$cid = safe_int($args['cid']);
	} else {
		if ( count($cids)==1 ) {
			$cid = reset($cids);
		} else {
			$api->createError("No contest ID specified but active contest is ambiguous.");
		}
	}

	$filter = array();
	if ( array_key_exists('category', $args) ) {
		$filter['categoryid'] = array($args['category']);
	}
	if ( array_key_exists('country', $args) ) {
		$filter['country'] = array($args['country']);
	}
	if ( array_key_exists('affiliation', $args) ) {
		$filter['affilid'] = array($args['affiliation']);
	}

	$scoreboard = genScoreBoard($cdatas[$cid], !$args['public'], $filter);

	$prob2label = $DB->q('KEYVALUETABLE SELECT probid, shortname
	                      FROM contestproblem WHERE cid = %i', $cid);

	$res = array();
	foreach ( $scoreboard['scores'] as $teamid => $data ) {
		$row = array('rank' => $data['rank'], 'team_id' => safe_string($teamid));
		$row['score'] = array('num_solved' => safe_int($data['num_points']),
		                      'total_time' => safe_int($data['total_time']));
		$row['problems'] = array();
		foreach ( $scoreboard['matrix'][$teamid] as $probid => $pdata ) {
			$prob = array('label'       => $prob2label[$probid],
			              'problem_id'  => safe_string($probid),
			              'num_judged'  => safe_int($pdata['num_submissions']),
			              'num_pending' => safe_int($pdata['num_pending']),
			              'solved'      => safe_bool($pdata['is_correct']));

			if ( $prob['solved'] ) {
				$prob['time'] = scoretime($pdata['time']);
				$first = first_solved($pdata['time'],
				                      $scoreboard['summary']['problems'][$probid]
				                      ['best_time_sort'][$data['sortorder']]);
				$prob['first_to_solve'] = safe_bool($first);
			}

			$row['problems'][] = $prob;
		}
		usort($row['problems'], 'cmp_prob_label');
		$res[] = $row;
	}
	return $res;
}
$doc = 'Get the scoreboard. Returns scoreboard for jury members if authenticated as a jury member (and public is not 1).';
$args = array('cid' => 'ID of the contest to get the scoreboard for.',
              'category' => 'ID of a single category to search for.',
              'affiliation' => 'ID of an affiliation to search for.',
              'country' => 'ISO 3166-1 alpha-3 country code to search for.');
$exArgs = array(array('cid' => 2, 'category' => 1, 'affiliation' => 'UU'),
                array('cid' => 2, 'country' => 'NLD'));
$api->provideFunction('GET', 'scoreboard', $doc, $args, $exArgs, null, true);

/**
 * Internal error reporting (back from judgehost)
 */
function internal_error_POST($args)
{
	global $DB;

	checkargs($args, array('description', 'judgehostlog', 'disabled'));

	global $cdatas, $api;

	// group together duplicate internal errors
	// note that it may be good to be able to ignore fields here, e.g. judgingid with compile errors
	$errorid = $DB->q('MAYBEVALUE SELECT errorid FROM internal_error
	                   WHERE description=%s AND disabled=%s AND status=%s' .
	                  ( isset($args['cid']) ? ' AND cid=%i' : '%_' ),
	                  $args['description'], $args['disabled'], 'open', $args['cid']);

	if ( isset($errorid) ) {
		// FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog
		return $errorid;
	}

	$errorid = $DB->q('RETURNID INSERT INTO internal_error
	                   (judgingid, cid, description, judgehostlog, time, disabled)
	                   VALUES (%i, %i, %s, %s, %i, %s)',
	                  $args['judgingid'], $args['cid'], $args['description'],
	                  $args['judgehostlog'], now(), $args['disabled']);

	$disabled = dj_json_decode($args['disabled']);
	// disable what needs to be disabled
	set_internal_error($disabled, $args['cid'], 0);
	if ( in_array($disabled['kind'], array('problem', 'language')) ) {
		// give back judging if we have to
		$submitid = $DB->q('VALUE SELECT submitid FROM judging WHERE judgingid = %i', $args['judgingid']);
		give_back_judging($args['judgingid'], $submitid);
	}

	return $errorid;
}
$doc = 'Report an internal error from the judgedaemon.';
$args = array('judgingid' => 'ID of the corresponding judging (if exists).',
	      'cid' => 'Contest ID.',
              'description' => 'short description',
              'judgehostlog' => 'last N lines of judgehost log',
              'disabled' => 'reason (JSON encoded)');
$exArgs = array();
$api->provideFunction('POST', 'internal_error', $doc, $args, $exArgs, null, true);

function judgement_types($args)
{
	global $VERDICTS;

	if ( isset($args['__primary_key']) ) {
		if ( isset($args['verdict']) ) {
			$api->createError("You cannot specify a primary ID both via /{id} and ?verdict={id}");
		}
		$args['verdict'] = $args['__primary_key'];
	}

	$res = array();
	foreach ( $VERDICTS as $name => $label ) {
		$penalty = TRUE;
		$solved = FALSE;
		if ( $name == 'correct' ) {
			$penalty = FALSE;
			$solved = TRUE;
		}
		if ( $name == 'compiler-error' ) {
			$penalty = dbconfig_get('compile_penalty', FALSE);
		}
		if ( isset($args['verdict']) && $label !== $args['verdict'] ) {
			continue;
		}
		$res[] = array(
			'id'      => $label,
			'label'   => $label,
			'name'    => str_replace('-',' ',$name),
			'penalty' => $penalty,
			'solved'  => $solved,
		);
	}

	return $res;
}
$doc = 'Lists all available judgement types.';
$args = array();
$exArgs = array();
$api->provideFunction('GET', 'judgement_types', $doc, $args, $exArgs, null, true);

// Now provide the api, which will handle the request
$api->provideApi();
