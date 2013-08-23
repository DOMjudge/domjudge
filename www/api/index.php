<?php
/**
 * DomJudge public REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

if ( ! defined('LIBWWWDIR') )
	require_once('../configure.php');

if ( ! defined('IS_JURY') ) define('IS_JURY', false);

// TODO: use IS_JURY constant in code below for access rights

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require(LIBWWWDIR . '/scoreboard.php');
require(LIBWWWDIR . '/restapi.php');

$cdata = getCurContest(TRUE);
$cid = (int)$cdata['cid'];


function infreeze($time) {
	if ( ( ! empty($cdata['freezetime']) &&
		difftime($time, $cdata['freezetime'])>0 ) &&
		!( ! empty($cdata['unfreezetime']) &&
		difftime($time, $cdata['unfreezetime'])<=0 ) ) return TRUE;
	return FALSE;
}

$api = new RestApi();

/**
 * API information
 */
function info() {
	return array('api_version' => DOMJUDGE_API_VERSION,
		'domjudge_version' => DOMJUDGE_VERSION);
}
$doc = "Get general API information.";
$api->provideFunction('GET', 'info', 'info', $doc);


/**
 * Contest information
 */
function contest() {
	global $cid, $cdata;

	return array('id'      => $cid,
		'name'    => $cdata['contestname'],
		'start'   => $cdata['starttime'],
		'end'     => $cdata['endtime']);
}
$doc = "Get information about the current contest: id, name, start and end.";
$api->provideFunction('GET', 'contest', 'contest', $doc);

/**
 * Problems information
 */
function problems() {
	global $cid, $DB;

	$q = $DB->q('SELECT probid, name, color FROM problem
		     WHERE cid = %i AND allow_submit = 1 ORDER BY probid', $cid);
	return $q->gettable();
}
$doc = "Get a list of problems in the contest, with for each problem: probid, name and color.";
$api->provideFunction('GET', 'problems', 'problems', $doc);

/**
 * Judgings information
 */
function judgings($args) {
	global $cid, $DB;

	$query = 'SELECT submitid, judgingid, eventtime FROM event WHERE cid = %i';
	if ( !IS_JURY ) {
		$query .= ' AND description = "problem judged"';
	}

	$hasFromid = array_key_exists('fromid', $args);
	$query .= ($hasFromid ? ' AND judgingid >= %i' : ' AND TRUE %_');
	$fromId = ($hasFromid ? $args['fromid'] : 0);

	$hasJudgingid = array_key_exists('judgingid', $args);
	$query .= ($hasJudgingid ? ' AND judgingid = %i' : ' AND TRUE %_');
	$judgingid = ($hasJudgingid ? $args['judgingid'] : 0);

	$query .= ' ORDER BY eventid';

	$hasLimit = array_key_exists('limit', $args);
	$query .= ($hasLimit ? ' LIMIT %i' : ' %_');
	$limit = ($hasLimit ? $args['limit'] : -1);
	// TODO: validate limit

	$q = $DB->q($query, $cid, $fromId, $judgingid, $limit);
	$res = array();
	while($row = $q->next()) {
		$data = $DB->q('MAYBETUPLE SELECT s.submittime, j.result FROM judging j
			        LEFT JOIN submission s ON (s.submitid = j.submitid)
			        WHERE j.judgingid = %i', $row['judgingid']);
		if ($data == NULL) continue;

		if ( !IS_JURY && infreeze($data['submittime']) ) continue;
		if ( array_key_exists('result', $args) && $args['result'] != $data['result']) continue; // This should be encoded directly in the query

		$res[] = array('judgingid' => $row['judgingid'], 'submitid' => $row['submitid'], 'result' => $data['result'], 'time' => $row['eventtime']);
	}
	return $res;
}
$doc = 'Get all judgings. Jury only? Or provide some limited list for the public?';
$args = array('result' => 'Search only for judgings with a certain result.',
              'fromid' => 'Search from a certain ID',
              'judgingid' => 'Search only for a certain ID',
              'limit' => 'Get only the first N judgings');
$exArgs = array(array('result' => 'correct'), array('fromid' => 800, 'limit' => 10));
$api->provideFunction('GET', 'judgings', 'judgings', $doc, $args, $exArgs);

function judgings_POST($args) {
	global $DB, $api;

	if ( !isset($args['judgehost']) ) {
		$api->createError("judgehost is mandatory");
	}

	$host = $args['judgehost'];
	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s', now(), $host);

	// If this judgehost is not active, there's nothing to do 
	$active = $DB->q('MAYBEVALUE SELECT active FROM judgehost WHERE hostname = %s', $host);
	if ( !$active ) return '';

	// we have to check for the judgability of problems/languages this way,
	// because we use an UPDATE below where joining is not possible.
	$probs = $DB->q('COLUMN SELECT probid FROM problem WHERE allow_judge = 1');
	if( count($probs) == 0 ) return '';
	$judgable_prob = array_unique(array_values($probs));

	$langs = $DB->q('COLUMN SELECT langid FROM language WHERE allow_judge = 1');
	if( count($langs) == 0 ) return '';
	$judgable_lang = array_unique(array_values($langs));

	$cdata = getCurContest(TRUE);
	$cid = $cdata['cid'];

	// Prioritize teams according to last judging time
	$submitid = $DB->q('MAYBEVALUE SELECT submitid
	                    FROM submission s
	                    LEFT JOIN team t ON (s.teamid = t.login)
	                    WHERE judgehost IS NULL AND cid = %i
	                    AND langid IN (%As) AND probid IN (%As)
	                    AND submittime < %s AND valid = 1
	                    ORDER BY judging_last_started ASC, submittime ASC, submitid ASC
	                    LIMIT 1',
	                    $cid, $judgable_lang, $judgable_prob,
	                    $cdata['endtime']);

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

	$row = $DB->q('TUPLE SELECT s.submitid, s.cid, s.teamid, s.probid, s.langid,
	               CEILING(time_factor*timelimit) AS maxruntime,	
	               special_run, special_compare
	               FROM submission s, problem p, language l
	               WHERE s.probid = p.probid AND s.langid = l.langid AND
	               submitid = %i', $submitid);

	$DB->q('UPDATE team SET judging_last_started = %s WHERE login = %s', now(), $row['teamid']);

	$query = 'RETURNID INSERT INTO judging (submitid,cid,starttime,judgehost) VALUES(%i,%i,%s,%s)';
	$jid = $DB->q($query, $row['submitid'], $row['cid'], now(), $host);

	$row['judgingid'] = $jid;

	return $row;
}
$doc = 'Request a new judging to be judged.';
$args = array('judgehost' => 'Judging is to be judged by this specific judgehost.');
$exArgs = array();
if ( IS_JURY ) {
	$api->provideFunction('POST', 'judgings', 'judgings_POST', $doc, $args, $exArgs);
}

function judgings_PUT($args) {
	global $DB, $api;

	if ( !isset($args['__primary_key']) ) {
		$api->createError("judgingid is mandatory");
	}
	$judgingid = $args['__primary_key'];
	if ( !isset($args['judgehost']) ) {
		$api->createError("judgehost is mandatory");
	}

	if ( isset($args['output_compile']) ) {
		// FIXME: uses NOW() in query
		$DB->q('UPDATE judging SET output_compile = %s' .
			($args['compile_success'] ? '' :
			', result = "compiler-error", endtime=NOW() ' ) .
			'WHERE judgingid = %i AND judgehost = %s',
			base64_decode($args['output_compile']),
			$judgingid, $args['judgehost']);

		$row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid FROM judging LEFT JOIN submission s USING(submitid) WHERE judgingid = %i',$judgingid);
		calcScoreRow($row['cid'], $row['teamid'], $row['probid']);
	}

	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s', now(), $args['judgehost']);

	return '';
}
$doc = 'Update a judging.';
$args = array('judgingid' => 'Judging corresponds to this specific judgingid.',
	'judgehost' => 'Judging is judged by this specific judgehost.',
	'compile_success' => 'Did the compilation succeed?',
	'output_compile' => 'Ouput of compilation phase.');
$exArgs = array();
if ( IS_JURY ) {
	$api->provideFunction('PUT', 'judgings', 'judgings_PUT', $doc, $args, $exArgs);
}

/**
 * Judging_Runs
 */
function judging_runs_POST($args) {
	global $DB, $api;

	if ( !isset($args['judgingid']) ) {
		$api->createError("judgingid is mandatory");
	}
	if ( !isset($args['testcaseid']) ) {
		$api->createError("testcaseid is mandatory");
	}
	if ( !isset($args['runresult']) ) {
		$api->createError("runresult is mandatory");
	}
	if ( !isset($args['runtime']) ) {
		$api->createError("runtime is mandatory");
	}
	if ( !isset($args['output_run']) ) {
		$api->createError("output_run is mandatory");
	}
	if ( !isset($args['output_diff']) ) {
		$api->createError("output_diff is mandatory");
	}
	if ( !isset($args['output_error']) ) {
		$api->createError("output_error is mandatory");
	}
	if ( !isset($args['judgehost']) ) {
		$api->createError("judgehost is mandatory");
	}

	$results_remap = dbconfig_get('results_remap');
	$results_prio = dbconfig_get('results_prio');

	if ( array_key_exists($args['runresult'], $results_remap) ) {
		logmsg(LOG_INFO, "Testcase $args[testcaseid] remapping result " . $args['runresult'] .
		                 " -> " . $results_remap[$args['runresult']]);
		$args['runresult'] = $results_remap[$args['runresult']];
	}

	$DB->q('INSERT INTO judging_run (judgingid, testcaseid, runresult,
		runtime, output_run, output_diff, output_error)
		VALUES (%i, %i, %s, %f, %s, %s, %s)',
			$args['judgingid'], $args['testcaseid'], $args['runresult'], $args['runtime'],
			base64_decode($args['output_run']),
			base64_decode($args['output_diff']),
			base64_decode($args['output_error']));
	
	// result of this judging_run has been stored. now check whether
	// we're done or if more testcases need to be judged.

	$probid = $DB->q('VALUE SELECT probid FROM testcase WHERE testcaseid = %i', $args['testcaseid']);

	$runresults = $DB->q('COLUMN SELECT runresult
		FROM judging_run LEFT JOIN testcase USING(testcaseid)
		WHERE judgingid = %i ORDER BY rank', $args['judgingid']);
	$numtestcases = $DB->q('VALUE SELECT count(*) FROM testcase WHERE probid = %s', $probid);

	$allresults = array_pad($runresults, $numtestcases, null);

	if ( ($result = getFinalResult($allresults, $results_prio))!==NULL ) {
		if ( count($runresults) == $numtestcases || dbconfig_get('lazy_eval_results', true) ) {
			$extrasql = ", endtime = NOW() ";
		} else { $extrasql = ""; }

		$DB->q('UPDATE judging SET result = %s' .$extrasql .
			'WHERE judgingid = %i', $result, $args['judgingid']);
		$row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid, s.langid, s.submitid FROM judging LEFT JOIN submission s USING(submitid) WHERE judgingid = %i',$args['judgingid']);
		calcScoreRow($row['cid'], $row['teamid'], $row['probid']);

		// log to event table if no verification required
		// (case of verification required is handled in www/jury/verify.php)
		if ( ! dbconfig_get('verification_required', 0) ) {
			$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid,
				submitid, judgingid, description)
				VALUES(%s, %i, %s, %s, %s, %i, %i, "problem judged")',
				now(), $row['cid'], $row['teamid'], $row['langid'], $row['probid'],
				$row['submitid'], $args['judgingid']);
			if ( $result == 'correct' ) {
				// prevent duplicate balloons in case of multiple correct submissions
				$numcorrect = $DB->q('VALUE SELECT count(submitid)
						      FROM balloon LEFT JOIN submission USING(submitid)
						      WHERE valid = 1 AND probid = %s AND teamid = %s',
						      $row['probid'], $row['teamid']);
				if ( $numcorrect == 0 ) {
					$DB->q('INSERT INTO balloon (submitid) VALUES(%i)',
						$row['submitid']);
				}
			}
		}

	}

	$DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s', now(), $args['judgehost']);

	return '';
}
$doc = 'Add a new judging_run to the list of judging_runs. When relevant, finalize the judging.';
$args = array('judgingid' => 'Judging_run corresponds to this specific judgingid.',
	'testcaseid' => 'Judging_run corresponding to this specific testcaseid.',
	'runresult' => 'Result of this run.',
	'runtime' => 'Runtime of this run.',
	'output_run' => 'Program output of this run.',
	'output_diff' => 'Program diff of this run.',
	'output_error' => 'Program error output of this run.',
	'judgehost' => 'Judgehost performing this judging');
$exArgs = array();
if ( IS_JURY ) {
	$api->provideFunction('POST', 'judging_runs', 'judging_runs_POST', $doc, $args, $exArgs);
}

/**
 * Result
 */
function results_POST($args) {
	global $cid, $DB;

	if ( !isset($args['result']) ) {
		$api->createError("result is mandatory");
	}
	if ( !isset($args['judgingid']) ) {
		$api->createError("judgingid is mandatory");
	}
	if ( !isset($args['judgehost']) ) {
		$api->createError("judgehost is mandatory");
	}
	if ( !isset($args['subinfo']) ) {
		$api->createError("subinfo is mandatory");
	}

	$row = json_decode($args['subinfo'], TRUE);

	// Start a transaction. This will provide extra safety if the table type
	// supports it.
	$DB->q('START TRANSACTION');
	// pop the result back into the judging table
	$DB->q('UPDATE judging SET result = %s, endtime = %s
		WHERE judgingid = %i AND judgehost = %s',
		$args['result'], now(), $args['judgingid'], $args['judgehost']);

	// recalculate the scoreboard cell (team,problem) after this judging
	calcScoreRow($cid, $row['teamid'], $row['probid']);

	// log to event table if no verification required
	// (case of verification required is handled in www/jury/verify.php)
	if ( ! dbconfig_get('verification_required', 0) ) {
		$DB->q('INSERT INTO event (eventtime, cid, teamid, langid, probid,
			submitid, judgingid, description)
			VALUES(%s, %i, %s, %s, %s, %i, %i, "problem judged")',
			now(), $cid, $row['teamid'], $row['langid'], $row['probid'],
			$row['submitid'], $args['judgingid']);
		if ( $args['result'] == 'correct' ) {
			// prevent duplicate balloons in case of multiple correct submissions
			$numcorrect = $DB->q('VALUE SELECT count(submitid)
				              FROM balloon LEFT JOIN submission USING(submitid)
				              WHERE valid = 1 AND probid = %s AND teamid = %s',
				              $row['probid'], $row['teamid']);
			if ( $numcorrect == 0 ) {
				$DB->q('INSERT INTO balloon (submitid) VALUES(%i)',
					$row['submitid']);
			}
		}
	}

	$DB->q('COMMIT');

	auditlog('judging', $args['judgingid'], 'judged',
		$args['result'], $args['judgehost']);
}
$doc = 'Stores final result.';
$args = array('judgingid' => 'Final result corresponds to this specific judgingid.',
	'result' => 'This is the final result of the judging.',
	'judgehost' => 'Judged by this judgehost.',
	'subinfo' => 'Additional information (teamid, probid, langid, submitid) of this run encoded as JSON.');
$exArgs = array();
if ( IS_JURY ) {
	$api->provideFunction('POST', 'results', 'results_POST', $doc, $args, $exArgs);
}

/**
 * DBconfiguration
 */
function config($args) {
	// Call dbconfig_init() to prevent using cached values.
	dbconfig_init();

	return array($args['name'] => dbconfig_get($args['name']));
}
$doc = 'Get configuration variables.';
$args = array('name' => 'Search only a single config variable.');
$exArgs = array(array('name' => 'sourcesize_limit'));
$api->provideFunction('GET', 'config', 'config', $doc, $args, $exArgs);

/**
 * Submissions information
 */
function submissions($args) {
	global $cid, $DB;

	$query = 'SELECT submitid, teamid, probid, langid, submittime, valid FROM submission WHERE cid = %i';

	$hasLanguage = array_key_exists('language', $args);
	$query .= ($hasLanguage ? ' AND langid = %s' : ' AND TRUE %_');
	$language = ($hasLanguage ? $args['language'] : 0);

	$hasFromid = array_key_exists('fromid', $args);
	$query .= ($hasFromid ? ' AND submitid >= %i' : ' AND TRUE %_');
	$fromId = ($hasFromid ? $args['fromid'] : 0);

	$hasSubmitid = array_key_exists('submitid', $args);
	$query .= ($hasSubmitid ? ' AND submitid = %i' : ' AND TRUE %_');
	$submitid = ($hasSubmitid ? $args['submitid'] : 0);

	$query .= ' ORDER BY submitid';

	$hasLimit = array_key_exists('limit', $args);
	$query .= ($hasLimit ? ' LIMIT %i' : ' %_');
	$limit = ($hasLimit ? $args['limit'] : -1);
	// TODO: validate limit

	$q = $DB->q($query, $cid, $language, $fromId, $submitid, $limit);
	$res = array();
	while($row = $q->next()) {
		$res[] = array('submitid' => $row['submitid'],
			'teamid' => $row['teamid'],
			'probid' => $row['probid'],
			'langid' => $row['langid'],
			'submittime' => $row['submittime'],
			'valid' => (bool)$row['valid']);
	}
	return $res;
}
$args = array('language' => 'Search only for submissions in a certain language.',
              'submitid' => 'Search only a certain ID',
              'fromid' => 'Search from a certain ID',
	      'limit' => 'Get only the first N submissions');
$doc = 'Get a list of all submissions. Should we give away all info about submissions? Or is there something we would like to hide, for example language?';
$exArgs = array(array('fromid' => 100, 'limit' => 10), array('language' => 'cpp'));
$api->provideFunction('GET', 'submissions', 'submissions', $doc, $args, $exArgs);

/**
 * Submission Files
 */
function submission_files($args) {
	global $DB, $api;

	if ( !isset($args['submitid']) ) {
		$api->createError("submitid is mandatory");
	}

	$sources = $DB->q('KEYTABLE SELECT rank AS ARRAYKEY, sourcecode, filename
			   FROM submission_file WHERE submitid = %i', $args['submitid']);

	foreach($sources as $r=>$src){
		$sources[$r]['sourcecode'] = base64_encode($sources[$r]['sourcecode']);
	}

	return $sources;
}
$args = array('submitid' => 'Get only the corresponding submission files.');
$doc = 'Get a list of all submission files. The file contents will be base64 encoded.';
$exArgs = array(array('submitid' => 3));
if ( IS_JURY ) {
	$api->provideFunction('GET', 'submission_files', 'submission_files', $doc, $args, $exArgs);
}

/**
 * Testcases
 */
function testcases($args) {
	global $DB, $api;

	if ( !isset($args['judgingid']) ) {
		$api->createError("judgingid is mandatory");
	}

	// endtime is set: judging is fully done; return empty
	$row = $DB->q('TUPLE SELECT endtime,probid
		FROM judging LEFT JOIN submission USING(submitid)
		WHERE judgingid = %i', $args['judgingid']);
	if ( !empty($row['endtime']) ) return '';

	$judging_runs = $DB->q("COLUMN SELECT testcaseid FROM judging_run WHERE judgingid = %i", $args['judgingid']);
	$sqlextra = count($judging_runs) ? "AND testcaseid NOT IN (%Ai)" : "%_";
	$testcase = $DB->q("MAYBETUPLE SELECT testcaseid, rank, probid, md5sum_input, md5sum_output
			     FROM testcase WHERE probid = %s $sqlextra ORDER BY rank LIMIT 1", $row['probid'], $judging_runs);

	// would probably never be empty, because then endtime would also have been set. we cope with it anyway for now.
	return is_null($testcase)?'':$testcase;
}
$args = array('judgingid' => 'Get the next-to-judge testcase for this judging.');
$doc = 'Get a testcase.';
if ( IS_JURY ) {
	$api->provideFunction('GET', 'testcases', 'testcases', $doc, $args);
}

function testcase_files($args) {
	global $DB, $api;

	if ( !isset($args['testcaseid']) ) {
		$api->createError("testcaseid is mandatory");
	}
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

	$content = $DB->q("VALUE SELECT SQL_NO_CACHE $inout FROM testcase
			   WHERE testcaseid = %i", $args['testcaseid']);

	return $content;
}
$args = array('testcaseid' => 'Get only the corresponding testcase.',
	'input' => 'Get the input file.',
	'output' => 'Get the output file.');
$doc = 'Get a testcase file.';
$exArgs = array(array('testcaseid' => '3', 'input' => TRUE));
if ( IS_JURY ) {
	$api->provideFunction('GET', 'testcase_files', 'testcase_files', $doc, $args, $exArgs);
}


/**
 * Judging Queue
 */
function queue($args) {
	global $DB;

	// TODO: make this configurable
	$cdata = getCurContest(TRUE);
	$cid = $cdata['cid'];

	// we have to check for the judgability of problems/languages this way,
	// because we use an UPDATE below where joining is not possible.
	$probs = $DB->q('COLUMN SELECT probid FROM problem WHERE allow_judge = 1');
	if( count($probs) == 0 ) {
		return '';
	}
	$judgable_prob = array_unique(array_values($probs));
	$langs = $DB->q('COLUMN SELECT langid FROM language WHERE allow_judge = 1');
	if( count($langs) == 0 ) {
		return '';
	}
	$judgable_lang = array_unique(array_values($langs));

	$hasLimit = array_key_exists('limit', $args);
	// TODO: validate limit

	$submitids = $DB->q('SELECT submitid
			     FROM submission s
			     LEFT JOIN team t ON (s.teamid = t.login)
			     WHERE judgehost IS NULL AND cid = %i
			     AND langid IN (%As) AND probid IN (%As)
			     AND submittime < %s AND valid = 1
			     ORDER BY judging_last_started ASC, submittime ASC, submitid ASC'
			     . ($hasLimit ? ' LIMIT %i' : ' %_'),
			     $cid, $judgable_lang, $judgable_prob,
			     $cdata['endtime'],
			     ($hasLimit ? $args['limit'] : -1));

	return $submitids->getTable();
}
$args = array('limit' => 'Get only the first N queued submissions');
$doc = 'Get a list of all queued submission ids.';
$exArgs = array(array('limit' => 10));
if ( IS_JURY ) {
	$api->provideFunction('GET', 'queue', 'queue', $doc, $args, $exArgs);
}

/**
 * Affiliation information
 */
function affiliations($args) {
	global $DB;

	// Construct query
	$query = 'SELECT affilid, name, country FROM team_affiliation WHERE';

	$byCountry = array_key_exists('country', $args);
	$query .= ($byCountry ? ' country = %s' : ' TRUE %_');
	$country = ($byCountry ? $args['country'] : '');

	$query .= ' ORDER BY name';

	// Run query and return result
	$q = $DB->q($query, $country);
	return $q->gettable();
}
$doc = 'Get a list of affiliations, with for each affiliation: affilid, name and country.';
$optArgs = array('country' => 'ISO 3166-1 alpha-3 country code to search for.');
$exArgs = array(array('country' => 'NLD'));
$api->provideFunction('GET', 'affiliations', 'affiliations', $doc, $optArgs, $exArgs);

/**
 * Team information
 */
function teams($args) {
	global $DB;

	// Construct query
	$query = 'SELECT login, name, categoryid as category, affilid as affiliation FROM team WHERE';

	$byCategory = array_key_exists('category', $args);
	$query .= ($byCategory ? ' categoryid = %i' : ' TRUE %_');
	$category = ($byCategory ? $args['category'] : 0);

	$query .= ' AND';

	$byAffil = array_key_exists('affiliation', $args);
	$query .= ($byAffil ? ' affilid = %s' : ' TRUE %_');
	$affiliation = ($byAffil ? $args['affiliation'] : 0);

	$byLogin = array_key_exists('login', $args);
	$query .= ($byLogin ? ' AND login = %s' : ' AND TRUE %_');
	$login = ($byLogin ? $args['login'] : 0);

	// Run query and return result
	$q = $DB->q($query, $category, $affiliation, $login);
	return $q->gettable();
}
$args = array('category' => 'ID of a single category to search for.',
              'affiliation' => 'ID of an affiliation to search for.',
              'login' => 'Search for a specific team.');
$doc = 'Get a list of teams containing login, name, category and affiliation.';
$exArgs = array(array('category' => 1, 'affiliation' => 'UU'));
$api->provideFunction('GET', 'teams', 'teams', $doc, $args, $exArgs);

/**
 * Category information
 */
function categories() {
	global $DB;

	$q = $DB->q('SELECT categoryid, name, color, visible FROM team_category ORDER BY sortorder');
	$res = array();
	while($row = $q->next()) {
		$res[] = array('categoryid' => $row['categoryid'],
			'name' => $row['name'],
			'color' => $row['color'],
			'visible' => (bool)$row['visible']);
	}
	return $res;
}
$doc = 'Get a list of all categories.';
$api->provideFunction('GET', 'categories', 'categories', $doc);

/**
 * Language information
 */
function languages() {
	global $DB;

	$q = $DB->q('SELECT langid, name, extensions, allow_submit, allow_judge, time_factor FROM language');
	$res = array();
	while($row = $q->next()) {
		$res[] = array('langid' => $row['langid'],
			'name' => $row['name'],
			'extensions' => json_decode($row['extensions']),
			'allow_judge' => (bool)$row['allow_judge'],
			'allow_submit' => (bool)$row['allow_submit'],
			'time_factor' => (float)$row['time_factor']);
	}
	return $res;
}
$doc = 'Get a list of all suported programming languages.';
$api->provideFunction('GET', 'languages', 'languages', $doc);

/**
 * Clarification information
 */
function clarifications($args) {
	global $cid, $DB;

	// Find public clarifications, maybe later also provide more info for jury
	$query = 'SELECT clarid, submittime, probid, body FROM clarification
		  WHERE cid = %i AND sender IS NULL AND recipient IS NULL';

	$byProblem = array_key_exists('problem', $args);
	$query .= ($byProblem ? ' AND probid = %s' : ' AND TRUE %_');
	$problem = ($byProblem ? $args['problem'] : null);

	$q = $DB->q($query, $cid, $problem);
	return $q->getTable();
}
$doc = 'Get a list of all public clarifications.';
$args = array('problem' => 'Search for clarifications about a specific problem.');
$exArgs = array(array('problem' => 'H'));
$api->provideFunction('GET', 'clarifications', 'clarifications', $doc, $args, $exArgs);

/**
 * Judgehosts
 */
function judgehosts($args) {
	global $DB;

	$query = 'SELECT hostname, active, polltime FROM judgehost';

	$byHostname = array_key_exists('hostname', $args);
	$query .= ($byHostname ? ' WHERE hostname = %s' : '%_');
	$hostname = ($byHostname ? $args['hostname'] : null);

	$q = $DB->q($query, $hostname);
	return $q->getTable();
}
$doc = 'Get a list of judgehosts.';
$args = array('hostname' => 'Search only for judgehosts with given hostname.');
$exArgs = array(array('hostname' => 'sparehost'));
if ( IS_JURY ) {
	$api->provideFunction('GET', 'judgehosts', 'judgehosts', $doc, $args, $exArgs);
}

function judgehosts_POST($args) {
	global $DB, $api;

	if ( !isset($args['hostname']) ) {
		$api->createError("hostname is mandatory");
	}

	$query = 'INSERT IGNORE INTO judgehost (hostname) VALUES(%s)';
	$q = $DB->q($query, $args['hostname']);

	// If there are any unfinished judgings in the queue in my name,
	// they will not be finished. Give them back.
	$res = $DB->q('SELECT judgingid, submitid, cid FROM judging WHERE
		       judgehost = %s AND endtime IS NULL AND valid = 1', $args['hostname']);
	$ret = $res->getTable();
	$res = $DB->q('SELECT judgingid, submitid, cid FROM judging WHERE
		       judgehost = %s AND endtime IS NULL AND valid = 1', $args['hostname']);
	while ( $jud = $res->next() ) {
		$DB->q('UPDATE judging SET valid = 0 WHERE judgingid = %i',
			$jud['judgingid']);
		$DB->q('UPDATE submission SET judgehost = NULL
			WHERE submitid = %i', $jud['submitid']);
		auditlog('judging', $jud['judgingid'], 'given back', null, $args['hostname']);
	}

	return $ret;
}
$doc = 'Add a new judgehost to the list of judgehosts. Also restarts (and returns) unfinished judgings.';
$args = array('hostname' => 'Add this specific judgehost and activate it.');
$exArgs = array(array('hostname' => 'judge007'));
if ( IS_JURY ) {
	$api->provideFunction('POST', 'judgehosts', 'judgehosts_POST', $doc, $args, $exArgs);
}

function judgehosts_PUT($args)  {
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
if ( IS_JURY ) {
	$api->provideFunction('PUT', 'judgehosts', 'judgehosts_PUT', $doc, $args, $exArgs);
}


/**
 * Scoreboard (not finished yet)
 */
function scoreboard($args) {
	global $cdata;
	$filter = array();
	if(array_key_exists('category', $args))
		$filter['categoryid'] = array($args['category']);
	if(array_key_exists('country', $args))
		$filter['country'] = array($args['country']);
	if(array_key_exists('affiliation', $args))
		$filter['affilid'] = array($args['affiliation']);
	// TODO: refine this output, maybe add separate function to get summary
	$scores = genScoreBoard($cdata, FALSE, $filter);
	return $scores['matrix'];
}

$args = array('category' => 'ID of a single category to search for.',
              'affiliation' => 'ID of an affiliation to search for.',
              'country' => 'ISO 3166-1 alpha-3 country code to search for.');
$doc = 'Get the scoreboard. Should give the same information as public/jury scoreboards, i.e. after freeze the public one is not updated.';
$exArgs = array(array('category' => 1, 'affiliation' => 'UU'), array('country' => 'NLD'));
$api->provideFunction('GET', 'scoreboard', 'scoreboard', $doc, $args, $exArgs);

// Now provide the api, which will handle the request
$api->provideApi();
