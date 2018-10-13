<?php declare(strict_types=1);
/**
 * DOMjudge public REST API
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

if (!defined('DOMJUDGE_API_VERSION')) {
    define('DOMJUDGE_API_VERSION', 4);
}

require('init.php');
require_once(LIBWWWDIR . '/common.jury.php');
use DOMJudgeBundle\Utils\Utils;

global $api;
if (!isset($api)) {
    function infreeze($cdata, $time)
    {
        if ((! empty($cdata['freezetime']) &&
             difftime($time, (float)$cdata['freezetime'])>=0) &&
            (empty($cdata['unfreezetime']) ||
             difftime($time, (float)$cdata['unfreezetime'])<0)) {
            return true;
        }
        return false;
    }

    function checkargs($args, $mandatory)
    {
        global $api;

        foreach ($mandatory as $arg) {
            if (!isset($args[$arg])) {
                $api->createError("argument '$arg' is mandatory");
                return false;
            }
        }

        return true;
    }

    function safe_int($value)
    {
        return is_null($value) ? null : (int)$value;
    }

    function safe_float($value, $decimals = null)
    {
        if (is_null($value)) {
            return null;
        }
        if (is_null($decimals)) {
            return (float)$value;
        }

        // Truncate the string version to a specified number of decimals,
        // since PHP floats seem not very reliable in not giving e.g.
        // 1.9999 instead of 2.0.
        $decpos = strpos((string)$value, '.');
        if ($decpos===false) {
            return (float)$value;
        }
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

    function give_back_judging($judgingid)
    {
        global $DB;

        $jdata = $DB->q('TUPLE SELECT judgingid, cid, submitid, judgehost, result
                         FROM judging WHERE judgingid = %i', $judgingid);

        $DB->q('START TRANSACTION');
        $DB->q('UPDATE judging SET valid = 0, rejudgingid = NULL WHERE judgingid = %i', $judgingid);
        $DB->q('UPDATE submission SET judgehost = NULL
                WHERE submitid = %i', $jdata['submitid']);
        $DB->q('COMMIT');

        auditlog('judging', $judgingid, 'given back', null, $jdata['judgehost'], $jdata['cid']);
        // TODO: consider judging deleted from API viewpoint?
    }

    $api = new RestApi();

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

    function judgings_POST($args)
    {
        global $DB, $api;

        if (!checkargs($args, array('judgehost'))) {
            return '';
        }

        $host = $args['judgehost'];
        $DB->q('UPDATE judgehost SET polltime = %f WHERE hostname = %s', now(), $host);

        // If this judgehost is not active, there's nothing to do
        $active = $DB->q('MAYBEVALUE SELECT active FROM judgehost WHERE hostname = %s', $host);
        if (!$active) {
            return '';
        }

        $cdatas = getCurContests(true);
        $cids = array_keys($cdatas);

        if (empty($cids)) {
            return '';
        }

        // Get judgehost restrictions
        $contests = array();
        $problems = array();
        $languages = array();
        $restrictions = $DB->q('MAYBEVALUE SELECT restrictions FROM judgehost
                                INNER JOIN judgehost_restriction USING (restrictionid)
                                WHERE hostname = %s', $host);
        if ($restrictions) {
            $restrictions = dj_json_decode($restrictions);
            $contests = @$restrictions['contest'];
            $problems = @$restrictions['problem'];
            $languages = @$restrictions['language'];
            $rejudge_own = @$restrictions['rejudge_own'];
        }

        $extra_join = '';
        $extra_where = '';
        if (empty($contests)) {
            $extra_where .= '%_ ';
        } else {
            $extra_where .= 'AND s.cid IN (%Ai) ';
        }

        if (empty($problems)) {
            $extra_where .= '%_ ';
        } else {
            $extra_join  .= 'LEFT JOIN problem p USING (probid) ';
            $extra_where .= 'AND s.probid IN (%Ai) ';
        }

        if (empty($languages)) {
            $extra_where .= '%_ ';
        } else {
            $extra_where .= 'AND s.langid IN (%As) ';
        }

        if (isset($rejudge_own) && (bool)$rejudge_own==false) {
            $extra_join  .= 'LEFT JOIN judging j ON (j.submitid=s.submitid AND j.judgehost=%s) ';
            $extra_where .= 'AND j.judgehost IS NULL ';
        } else {
            $extra_join  .= '%_ ';
        }


        // Prioritize teams according to last judging time
        $submitids = $DB->q('COLUMN SELECT s.submitid
                             FROM submission s
                             LEFT JOIN team t USING (teamid)
                             LEFT JOIN language l USING (langid)
                             LEFT JOIN contestproblem cp USING (probid, cid) ' .
                            $extra_join .
                            'WHERE s.judgehost IS NULL AND s.cid IN (%Ai)
                             AND l.allow_judge = 1 AND cp.allow_judge = 1 AND s.valid = 1 ' .
                            $extra_where .
                            'ORDER BY judging_last_started ASC, submittime ASC, s.submitid ASC',
                            $host, $cids, $contests, $problems, $languages);

        foreach ($submitids as $submitid) {
            // update exactly one submission with our judgehost name
            // Note: this might still return 0 if another judgehost beat
            // us to it
            $numupd = $DB->q('RETURNAFFECTED UPDATE submission
                              SET judgehost = %s
                              WHERE submitid = %i AND judgehost IS NULL',
                             $host, $submitid);

            if ($numupd==1) {
                break;
            }
        }

        if (empty($submitid) || $numupd == 0) {
            return '';
        }

        $row = $DB->q('TUPLE SELECT s.submitid, s.cid, s.teamid, s.probid, s.langid,
                       s.rejudgingid, s.entry_point, s.origsubmitid,
                       time_factor*timelimit AS maxruntime,
                       p.memlimit, p.outputlimit,
                       special_run AS run, special_compare AS compare,
                       special_compare_args AS compare_args, compile_script
                       FROM submission s
                       LEFT JOIN problem p USING (probid)
                       LEFT JOIN language l USING (langid)
                       WHERE submitid = %i', $submitid);

        $DB->q('UPDATE team SET judging_last_started = %f WHERE teamid = %i',
               now(), $row['teamid']);

        if (empty($row['memlimit'])) {
            $row['memlimit'] = dbconfig_get('memory_limit');
        }
        if (empty($row['outputlimit'])) {
            $row['outputlimit'] = dbconfig_get('output_limit');
        }
        if (empty($row['compare'])) {
            $row['compare'] = dbconfig_get('default_compare');
        }
        if (empty($row['run'])) {
            $row['run'] = dbconfig_get('default_run');
        }

        $compare_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
                                  WHERE execid = %s', $row['compare']);
        $row['compare_md5sum'] = $compare_md5sum;
        $run_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
                              WHERE execid = %s', $row['run']);
        $row['run_md5sum'] = $run_md5sum;
        if (!empty($row['compile_script'])) {
            $compile_script_md5sum = $DB->q('MAYBEVALUE SELECT md5sum FROM executable
                                             WHERE execid = %s', $row['compile_script']);
            $row['compile_script_md5sum'] = $compile_script_md5sum;
        }

        $is_rejudge = isset($row['rejudgingid']);
        if ($is_rejudge) {
            // FIXME: what happens if there is no valid judging?
            $prev_rejudgingid = $DB->q('MAYBEVALUE SELECT judgingid
                                        FROM judging
                                        WHERE submitid=%i AND valid=1', $submitid);
        }
        $is_editsubmit = isset($row['origsubmitid']);
        $jury_member = '';
        if ($is_editsubmit) {
            $jury_members = $DB->q('SELECT username FROM user
                                    JOIN team USING (teamid)
                                    JOIN submission USING (teamid)
                                    WHERE submitid = %s', $submitid);
            if ($jury_members->count() != 1) {
                $id_editsubmit = false; // Really is edit/submit but no single owner
            } else {
                $jury_member = $jury_members->next()['username'];
            }
        }

        $DB->q('START TRANSACTION');

        $jid = $DB->q('RETURNID INSERT INTO judging (submitid,cid,starttime,judgehost' .
                      ($is_rejudge ? ', rejudgingid, prevjudgingid, valid' : '') .
                      ($is_editsubmit ? ', jury_member' : '') .
                      ') VALUES(%i,%i,%f,%s' .
                      ($is_rejudge ? ',%i,%i,%i' : '%_ %_ %_') .
                      ($is_editsubmit ? ',%s' : '%_') .
                      ')',
                      $submitid, $row['cid'], now(), $host, @$row['rejudgingid'],
                      @$prev_rejudgingid, !$is_rejudge, $jury_member);

        if (!$is_rejudge) {
            eventlog('judging', $jid, 'create', $row['cid']);
        }

        $DB->q('COMMIT');

        $row['submitid']    = safe_int($row['submitid']);
        $row['cid']         = safe_int($row['cid']);
        $row['teamid']      = safe_int($row['teamid']);
        $row['probid']      = safe_int($row['probid']);
        $row['langid']      = $row['langid'];
        $row['rejudgingid'] = safe_int($row['rejudgingid']);
        $row['maxruntime']  = safe_float($row['maxruntime'], 6);
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

        if (!isset($args['__primary_key'])) {
            $api->createError("judgingid is mandatory");
            return '';
        }
        if (count($args['__primary_key']) > 1) {
            $api->createError("only one judgingid is allowed");
            return '';
        }
        $judgingid = reset($args['__primary_key']);
        if (!isset($args['judgehost'])) {
            $api->createError("judgehost is mandatory");
            return '';
        }

        if (isset($args['output_compile'])) {
            if (isset($args['entry_point'])) {
                // We're updating the entry_point after submission time. This
                // probably does not work well when forwarding to another CCS.
                $subm = $DB->q('TUPLE SELECT s.cid, s.submitid
                                FROM judging j
                                LEFT JOIN submission s USING(submitid)
                                WHERE j.judgingid = %i', $judgingid);

                $DB->q('START TRANSACTION');
                $DB->q('UPDATE submission SET entry_point = %s
                        WHERE submitid = %i', $args['entry_point'], $subm['submitid']);

                eventlog('submission', $subm['submitid'], 'update', $subm['cid']);
                $DB->q('COMMIT');
            }
            if ($args['compile_success']) {
                $DB->q('UPDATE judging SET output_compile = %s
                        WHERE judgingid = %i AND judgehost = %s',
                       base64_decode($args['output_compile']), $judgingid, $args['judgehost']);
            } else {
                $row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid, s.langid, s.submitid, s.rejudgingid
                               FROM judging
                               LEFT JOIN submission s USING(submitid)
                               WHERE judgingid = %i', $judgingid);

                $DB->q('START TRANSACTION');
                $DB->q('UPDATE judging SET output_compile = %s,
                        result = "compiler-error", endtime = %f
                        WHERE judgingid = %i AND judgehost = %s',
                       base64_decode($args['output_compile']),
                       now(), $judgingid, $args['judgehost']);

                auditlog('judging', $judgingid, 'judged', 'compiler-error', $args['judgehost'], $row['cid']);

                // log to event table if no verification required
                // (case of verification required is handled in www/jury/verify.php)
                if (! dbconfig_get('verification_required', 0) && !isset($row['rejudgingid'])) {
                    eventlog('judging', $judgingid, 'update', $row['cid']);
                }
                $DB->q('COMMIT');

                calcScoreRow((int)$row['cid'], (int)$row['teamid'], (int)$row['probid']);

                // We call alert here for the failed submission. Note that
                // this means that these alert messages should be treated
                // as confidential information.
                alert('reject', "submission $row[submitid], judging $judgingid: compiler-error");
            }
        }

        $DB->q('UPDATE judgehost SET polltime = %f WHERE hostname = %s',
               now(), $args['judgehost']);

        return '';
    }
    $doc = 'Update a judging.';
    $args = array('judgingid' => 'Judging corresponds to this specific judgingid.',
                  'judgehost' => 'Judging is judged by this specific judgehost.',
                  'compile_success' => 'Did the compilation succeed?',
                  'output_compile' => 'Ouput of compilation phase (base64 encoded).',
                  'entry_point' => 'Optional entry point auto-detected during compilation.');
    $exArgs = array();
    $roles = array('judgehost');
    $api->provideFunction('PUT', 'judgings', $doc, $args, $exArgs, $roles);

    /**
     * Judging_Runs
     */
    function judging_runs_POST($args)
    {
        global $DB, $api;

        if (!checkargs($args, array('judgingid', 'testcaseid', 'runresult', 'runtime',
                           'output_run', 'output_diff', 'output_error', 'output_system', 'judgehost'))) {
            return '';
        }

        $results_remap = dbconfig_get('results_remap');
        $results_prio = dbconfig_get('results_prio');

        if (array_key_exists($args['runresult'], $results_remap)) {
            logmsg(LOG_INFO, "Testcase $args[testcaseid] remapping result " . $args['runresult'] .
                         " -> " . $results_remap[$args['runresult']]);
            $args['runresult'] = $results_remap[$args['runresult']];
        }

        $jud = $DB->q('TUPLE SELECT judgingid, cid, result, rejudgingid
                       FROM judging
                       WHERE judgingid = %i', $args['judgingid']);

        $DB->q('START TRANSACTION');

        $runid = $DB->q('RETURNID INSERT INTO judging_run (judgingid, testcaseid, runresult,
                         runtime, endtime, output_run, output_diff, output_error, output_system)
                         VALUES (%i, %i, %s, %f, %f, %s, %s, %s, %s)',
                        $args['judgingid'], $args['testcaseid'], $args['runresult'],
                        $args['runtime'], now(),
                        base64_decode($args['output_run']),
                        base64_decode($args['output_diff']),
                        base64_decode($args['output_error']),
                        base64_decode($args['output_system']));

        if (!isset($jud['rejudgingid'])) {
            eventlog('judging_run', $runid, 'create', $jud['cid']);
        }

        $DB->q('COMMIT');

        // result of this judging_run has been stored. now check whether
        // we're done or if more testcases need to be judged.

        $probid = $DB->q('VALUE SELECT probid FROM testcase
                          WHERE testcaseid = %i', $args['testcaseid']);

        $runresults = $DB->q('COLUMN SELECT runresult
                              FROM judging_run LEFT JOIN testcase USING(testcaseid)
                              WHERE judgingid = %i ORDER BY rank', $args['judgingid']);
        $numtestcases = $DB->q('VALUE SELECT count(*) FROM testcase WHERE probid = %i', $probid);

        $allresults = array_pad($runresults, (int)$numtestcases, null);

        if (($result = getFinalResult($allresults, $results_prio))!==null) {

        // Lookup global lazy evaluation of results setting and
            // possible problem specific override.
            $lazy_eval = dbconfig_get('lazy_eval_results', true);
            $prob_lazy = $DB->q('MAYBEVALUE SELECT cp.lazy_eval_results
                                 FROM judging j
                                 LEFT JOIN submission s USING(submitid)
                                 LEFT JOIN contestproblem cp ON (cp.cid=j.cid AND cp.probid=s.probid)
                                 WHERE judgingid = %i', $args['judgingid']);
            if (isset($prob_lazy)) {
                $lazy_eval = (bool)$prob_lazy;
            }

            if (count($runresults) == $numtestcases || $lazy_eval) {
                // NOTE: setting endtime here determines in testcases_GET
                // whether a next testcase will be handed out.
                $DB->q('UPDATE judging SET result = %s, endtime = %f
                        WHERE judgingid = %i', $result, now(), $args['judgingid']);
            } else {
                $DB->q('UPDATE judging SET result = %s
                        WHERE judgingid = %i', $result, $args['judgingid']);
            }

            // Only update if the current result is different from what we
            // had before. This should only happen when the old result was
            // NULL.
            if ($jud['result'] !== $result) {
                if ($jud['result'] !== null) {
                    error('internal bug: the evaluated result changed during judging');
                }

                $row = $DB->q('TUPLE SELECT s.cid, s.teamid, s.probid, s.langid, s.submitid
                               FROM judging
                               LEFT JOIN submission s USING(submitid)
                               WHERE judgingid = %i', $args['judgingid']);
                calcScoreRow((int)$row['cid'], (int)$row['teamid'], (int)$row['probid']);

                // We call alert here before possible validation. Note
                // that this means that these alert messages should be
                // treated as confidential information.
                alert(
                    ($result==='correct' ? 'accept' : 'reject'),
                    "submission $row[submitid], judging $args[judgingid]: $result"
                );

                // log to event table if no verification required
                // (case of verification required is handled in www/jury/verify.php)
                if (! dbconfig_get('verification_required', 0)) {
                    if (!isset($jud['rejudgingid'])) {
                        eventlog('judging', (int)$args['judgingid'], 'update', (int)$row['cid']);
                        updateBalloons((int)$row['submitid']);
                    }
                }

                auditlog('judging', (int)$args['judgingid'], 'judged', $result, $args['judgehost']);

                $just_finished = true;
            }
        }

        // Send an event for an endtime update if not done yet.
        if (!isset($jud['rejudgingid']) &&
            count($runresults) == $numtestcases && empty($just_finished)) {
            eventlog('judging', $args['judgingid'], 'update', $jud['cid']);
        }

        $DB->q('UPDATE judgehost SET polltime = %f WHERE hostname = %s',
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
     * POST a new submission
     */
    function submissions_POST($args)
    {
        global $userdata, $DB, $api;
        if (!checkargs($args, array('shortname','langid'))) {
            return '';
        }
        if (!checkargs($userdata, array('teamid'))) {
            return '';
        }
        $contests = getCurContests(true, $userdata['teamid'], false, 'shortname');
        $contest_shortname = null;

        if (isset($args['contest'])) {
            if (isset($contests[$args['contest']])) {
                $contest_shortname = $args['contest'];
            } else {
                $api->createError("Cannot find active contest '$args[contest]', or you are not part of it.");
                return '';
            }
        } else {
            if (count($contests) == 1) {
                $contest_shortname = key($contests);
            } else {
                $api->createError("No contest specified while multiple active contests found.");
                return '';
            }
        }
        $cid = $contests[$contest_shortname]['cid'];

        $probid = $DB->q('MAYBEVALUE SELECT probid FROM problem
                          INNER JOIN contestproblem USING (probid)
                          WHERE shortname = %s AND cid = %i AND allow_submit = 1',
                         $args['shortname'], $cid);
        if (empty($probid)) {
            error("Problem " . $args['shortname'] . " not found or or not submittable");
        }

        // rebuild array of filenames, paths to get rid of empty upload fields
        $FILEPATHS = $FILENAMES = array();
        foreach ($_FILES['code']['tmp_name'] as $fileid => $tmpname) {
            if (!empty($tmpname)) {
                checkFileUpload($_FILES['code']['error'][$fileid]);
                $FILEPATHS[] = $_FILES['code']['tmp_name'][$fileid];
                $FILENAMES[] = $_FILES['code']['name'][$fileid];
            }
        }

        $lang = $DB->q('MAYBETUPLE SELECT langid, name, require_entry_point, entry_point_description
                        FROM language
                        WHERE langid = %s AND allow_submit = 1', $args['langid']);

        if (! isset($lang)) {
            error("Unable to find language '$args[langid]' or not submittable");
        }
        $langid = $lang['langid'];

        $entry_point = null;
        if ($lang['require_entry_point']) {
            if (empty($args['entry_point'])) {
                $ep_desc = ($lang['entry_point_description'] ? : 'Entry point');
                error("$ep_desc required, but not specified.");
            }
            $entry_point = $args['entry_point'];
        }

        $sid = submit_solution((int)$userdata['teamid'], (int)$probid, (int)$cid, $langid, $FILEPATHS, $FILENAMES, null, $entry_point);

        auditlog('submission', $sid, 'added', 'via api', null, $cid);

        return safe_int($sid);
    }

    $args = array(
        'code[]' => 'Array of source files to submit',
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

        if (!checkargs($args, array('submission_id'))) {
            return '';
        }

        $sources = $DB->q('SELECT submitfileid, submitid, filename, sourcecode FROM submission_file
                           WHERE submitid = %i ORDER BY rank', $args['submission_id']);

        if ($sources->count()==0) {
            $api->createError("Cannot find source files for submission '$args[id]'.");
            return '';
        }

        $ret = array();
        while ($src = $sources->next()) {
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

        if (!checkargs($args, array('judgingid'))) {
            return '';
        }

        // endtime is set: judging is fully done; return empty
        $row = $DB->q('TUPLE SELECT endtime,probid
                       FROM judging LEFT JOIN submission USING(submitid)
                       WHERE judgingid = %i', $args['judgingid']);
        if (!empty($row['endtime'])) {
            return '';
        }

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

        if (!checkargs($args, array('testcaseid'))) {
            return '';
        }

        if (!isset($args['input']) && !isset($args['output'])) {
            $api->createError("either input or output is mandatory");
            return '';
        }
        if (isset($args['input']) && isset($args['output'])) {
            $api->createError("cannot select both input and output");
            return '';
        }
        $inout = 'output';
        if (isset($args['input'])) {
            $inout = 'input';
        }

        $content = $DB->q("MAYBEVALUE SELECT SQL_NO_CACHE $inout FROM testcase
                           WHERE testcaseid = %i", $args['testcaseid']);

        if (is_null($content)) {
            $api->createError("Cannot find testcase '$args[testcaseid]'.");
            return '';
        }

        return base64_encode($content);
    }
    $args = array('testcaseid' => 'Get only the corresponding testcase.',
                  'input' => 'Get the input file.',
                  'output' => 'Get the output file.');
    $doc = 'Get a testcase file, base64 encoded.';
    $exArgs = array(array('testcaseid' => '3', 'input' => true));
    $roles = array('jury','judgehost');
    $api->provideFunction('GET', 'testcase_files', $doc, $args, $exArgs, $roles);

    // executable zip, e.g. for compare scripts
    function executable($args)
    {
        global $DB, $api;

        if (!checkargs($args, array('execid'))) {
            return '';
        }

        $content = $DB->q("MAYBEVALUE SELECT SQL_NO_CACHE zipfile FROM executable
                           WHERE execid = %s", $args['execid']);

        if (is_null($content)) {
            $api->createError("Cannot find executable '$args[execid]'.");
            return '';
        }

        return base64_encode($content);
    }
    $args = array('execid' => 'Get only the corresponding executable.');
    $doc = 'Get an executable zip file, base64 encoded.';
    $exArgs = array(array('execid' => 'ignorews'));
    $roles = array('jury','judgehost');
    $api->provideFunction('GET', 'executable', $doc, $args, $exArgs, $roles);

    function judgehosts_POST($args)
    {
        global $DB, $api;

        if (!checkargs($args, array('hostname'))) {
            return '';
        }

        $DB->q('INSERT IGNORE INTO judgehost (hostname) VALUES(%s)', $args['hostname']);

        // If there are any unfinished judgings in the queue in my name,
        // they will not be finished. Give them back.
        $query = 'TABLE SELECT judgingid, submitid, cid
                  FROM judging j
                  LEFT JOIN rejudging r USING (rejudgingid)
                  WHERE judgehost = %s AND j.endtime IS NULL
                  AND (j.valid = 1 OR r.valid = 1)';
        $res = $DB->q($query, $args['hostname']);
        foreach ($res as $jud) {
            give_back_judging($jud['judgingid']);
        }

        return array_map(function ($jud) {
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

        if (!isset($args['__primary_key'])) {
            $api->createError("hostname is mandatory");
            return '';
        }
        if (count($args['__primary_key']) > 1) {
            $api->createError("only one hostname allowed");
            return '';
        }
        $hostname = reset($args['__primary_key']);
        if (!isset($args['active'])) {
            $api->createError("active is mandatory");
            return '';
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
    function cmp_prob_label($a, $b)
    {
        return $a['label'] > $b['label'];
    }

    /**
     * Scoreboard
     */
    function scoreboard($args)
    {
        global $DB, $api, $userdata, $cdatas, $cids, $requestedCid;

        if (isset($userdata['teamid'])) {
            $cdatas = getCurContests(true, $userdata['teamid']);
        }

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)==1) {
                $cid = reset($cids);
            } else {
                $api->createError("No contest ID specified but active contest is ambiguous.");
                return '';
            }
        }

        $only_visible_teams = true;
        if (isset($args['allteams']) && $args['allteams']) {
            $only_visible_teams = false;
        }

        $filter = array();
        if (array_key_exists('category', $args)) {
            $filter['categoryid'] = array($args['category']);
        }
        if (array_key_exists('country', $args)) {
            $filter['country'] = array($args['country']);
        }
        if (array_key_exists('affiliation', $args)) {
            $filter['affilid'] = array($args['affiliation']);
        }

        $scoreboard = genScoreBoard($cdatas[$cid], !$args['public'], $filter, $only_visible_teams);

        $prob2label = $DB->q('KEYVALUETABLE SELECT probid, shortname
                              FROM contestproblem WHERE cid = %i', $cid);

        $res = array();
        foreach ($scoreboard['scores'] as $teamid => $data) {
            $row = array('rank' => $data['rank'],
                     'team_id' => safe_string(rest_extid('teams', $teamid)));
            $row['score'] = array('num_solved' => safe_int($data['num_points']),
                              'total_time' => safe_int($data['total_time']));
            $row['problems'] = array();
            foreach ($scoreboard['matrix'][$teamid] as $probid => $pdata) {
                $prob = array(
                    'label'       => $prob2label[$probid],
                    'problem_id'  => safe_string(rest_extid('problems', $probid)),
                    'num_judged'  => safe_int($pdata['num_submissions']),
                    'num_pending' => safe_int($pdata['num_pending']),
                    'solved'      => safe_bool($pdata['is_correct'])
                );
                if ($prob['solved']) {
                    $prob['time'] = scoretime((float)$pdata['time']);
                    // TODO: according the API specification this doesn't
                    // have to be added. Also, the current first_solved()
                    // implementation is incorrent when there are pending
                    // earlier submissions.
                    /*
                    $first = first_solved(
                        $pdata['time'],
                        $scoreboard['summary']['problems'][$probid]
                        ['best_time_sort'][$data['sortorder']]
                    );
                    $prob['first_to_solve'] = safe_bool($first);
                    */
                }

                $row['problems'][] = $prob;
            }
            usort($row['problems'], 'cmp_prob_label');

            if (isset($args['strict'])) {
                foreach ($row['problems'] as $key => $data) {
                    unset($row['problems'][$key]['label']);
                }
            }

            $res[] = $row;
        }
        return $res;
    }
    $doc = 'Get the scoreboard. Returns scoreboard for jury members if authenticated as a jury member (and public is not 1).';
    $args = array('cid' => 'ID of the contest to get the scoreboard for.',
                  'category' => 'ID of a single category to search for.',
                  'affiliation' => 'ID of an affiliation to search for.',
                  'country' => 'ISO 3166-1 alpha-3 country code to search for.',
                  'allteams' => 'If set to true-ish and with jury permissions, also output invisible teams.');
    $exArgs = array(array('cid' => 2, 'category' => 1, 'affiliation' => 'UU'),
                array('cid' => 2, 'country' => 'NLD'));
    $api->provideFunction('GET', 'scoreboard', $doc, $args, $exArgs, null, true);

    /**
     * Internal error reporting (back from judgehost)
     */
    function internal_error_POST($args)
    {
        global $DB, $cdatas, $api;

        if (!checkargs($args, array('description', 'judgehostlog', 'disabled'))) {
            return '';
        }

        // Both cid and judgingid are allowed to be NULL.
        $cid = @$args['cid'];
        $judgingid = @$args['judgingid'];

        // group together duplicate internal errors
        // note that it may be good to be able to ignore fields here, e.g. judgingid with compile errors
        $errorid = $DB->q('MAYBEVALUE SELECT errorid FROM internal_error
                           WHERE description=%s AND disabled=%s AND status=%s' .
                          (isset($cid) ? ' AND cid=%i' : '%_'),
                          $args['description'], $args['disabled'], 'open', $cid);

        if (isset($errorid)) {
            // FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog
            return $errorid;
        }

        $errorid = $DB->q('RETURNID INSERT INTO internal_error
                           (judgingid, cid, description, judgehostlog, time, disabled)
                           VALUES (%i, %i, %s, %s, %f, %s)',
                          $judgingid, $cid, $args['description'],
                          $args['judgehostlog'], now(), $args['disabled']);

        $disabled = dj_json_decode($args['disabled']);
        // disable what needs to be disabled
        set_internal_error($disabled, $cid, 0);
        if (in_array($disabled['kind'], array('problem', 'language', 'judgehost'))  && isset($args['judgingid'])) {
            // give back judging if we have to
            give_back_judging($args['judgingid']);
        }

        return $errorid;
    }
    $doc = 'Report an internal error from the judgedaemon.';
    $args = array('judgingid' => 'ID of the corresponding judging (if exists).',
                  'cid' => 'Contest ID (if associated to one).',
                  'description' => 'short description',
                  'judgehostlog' => 'last N lines of judgehost log',
                  'disabled' => 'reason (JSON encoded)');
    $exArgs = array();
    $roles = array('judgehost');
    $api->provideFunction('POST', 'internal_error', $doc, $args, $exArgs, $roles, true);
}

// Now provide the api, which will handle the request
$api->provideApi(true);
