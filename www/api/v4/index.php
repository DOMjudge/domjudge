<?php
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
             difftime($time, $cdata['freezetime'])>=0) &&
            (empty($cdata['unfreezetime']) ||
             difftime($time, $cdata['unfreezetime'])<0)) {
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

    /**
     * API information
     */
    function info()
    {
        return array(
            'api_version' => DOMJUDGE_API_VERSION,
            'domjudge_version' => DOMJUDGE_VERSION
        );
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

    function status()
    {
        global $DB, $api, $cdatas, $userdata, $cids, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        $ret = array();
        $ret['num_submissions'] = $DB->q(
            'VALUE SELECT COUNT(s.submitid)
             FROM submission s
             WHERE s.cid=%s',
            $cid
        );
        $ret['num_queued'] = $DB->q(
            'VALUE SELECT COUNT(*)
             FROM submission s
             LEFT JOIN judging j ON (j.submitid = s.submitid AND j.valid != 0)
             WHERE s.cid=%s
             AND result IS NULL
             AND s.valid = 1',
            $cid
        );
        $ret['num_judging'] = $DB->q(
            'VALUE SELECT COUNT(*)
             FROM submission s
             LEFT JOIN judging j USING (submitid)
             WHERE s.cid=%s
             AND result IS NULL
             AND j.valid = 1
             AND s.valid = 1',
            $cid
        );
        return $ret;
    }
    $api->provideFunction('GET', 'status', 'Undocumented for now.', array(), array(), array('jury'));

    /**
     * Contest information
     */
    function contest()
    {
        global $cids, $cdatas, $userdata;

        if (checkrole('jury')) {
            $cdatas = getCurContests(true);
        } elseif (isset($userdata['teamid'])) {
            $cdatas = getCurContests(true, $userdata['teamid']);
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

        if (checkrole('jury')) {
            $cdatas = getCurContests(true);
        } elseif (isset($userdata['teamid'])) {
            $cdatas = getCurContests(true, $userdata['teamid']);
        }

        return array_map("cdataHelper", array_values($cdatas));
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
        global $DB, $api, $cdatas, $userdata, $cids, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['probid'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?probid={id}");
                return '';
            }
            $args['probids'] = array_map(function ($probId) use ($cid) {
                return rest_intid('problems', $probId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['probid'])) {
            $args['probids'] = [$args['probid']];
        }

        // Check that user has access to the problems in this contest:
        if (checkrole('team')) {
            $cdatas = getCurContests(true, $userdata['teamid']);
        }
        if (checkrole('jury') ||
            (isset($cdatas[$cid]) && difftime(now(), $cdatas[$cid]['starttime'])>=0)) {

        // We sort the problems by shortname, i.e in the same way we
            // sort them in the scoreboard, and return all. Then we assign
            // the ordinal and finally select a single problem in code to
            // make sure that the ordinal is the same if we query a single
            // problem.
            $pdatas = $DB->q('TABLE SELECT probid AS id, shortname AS label, shortname,
                                           name, color, timelimit,
                                           COUNT(testcaseid) AS test_data_count
                              FROM problem
                              INNER JOIN contestproblem USING (probid)
                              LEFT JOIN testcase USING (probid)
                              WHERE cid = %i AND allow_submit = 1
                              GROUP BY probid ORDER BY shortname', $cid);
        } else {
            $pdatas = array();
        }

        $ordinal = 0;
        $res = array();
        foreach ($pdatas as $pdata) {
            if (!isset($pdata['color'])) {
                unset($pdata['color']);
            } elseif (preg_match('/^#[[:xdigit:]]{3,6}$/', $pdata['color'])) {
                $pdata['rgb'] = $pdata['color'];
                $pdata['color'] = hex_to_color($pdata['color']);
            } else {
                $pdata['rgb'] = color_to_hex($pdata['color']);
            }
            $pdata['ordinal'] = $ordinal++;
            // If specified, select a single problem after assigning ordinals.
            if (!array_key_exists('probids', $args) || in_array($pdata['id'], $args['probids'])) {
                $res[] = $pdata;
            }
        }

        $is_jury = checkrole('jury');
        return array_map(function ($pdata) use ($is_jury, $args) {
            $ret = array(
                'id'         => safe_string(rest_extid('problems', $pdata['id'])),
                'label'      => safe_string($pdata['label']),
                'name'       => $pdata['name'],
                'ordinal'    => safe_int($pdata['ordinal']),
                'time_limit' => safe_float($pdata['timelimit'], 3),
            );
            if (!isset($args['strict'])) {
                $ret['short_name'] = $pdata['shortname'];
            }
            if (!empty($pdata['rgb'])) {
                $ret['rgb'] = $pdata['rgb'];
            }
            if (!empty($pdata['color'])) {
                $ret['color'] = $pdata['color'];
            }
            if ($is_jury) {
                $ret['test_data_count'] = safe_int($pdata['test_data_count']);
            }
            return $ret;
        }, $res);
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
        global $DB, $api, $userdata, $cdatas, $cids, $VERDICTS, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['judging_id'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?judging_id={id}");
                return '';
            }
            $args['judging_ids'] = array_map(function ($judgingId) use ($cid) {
                return rest_intid('judgements', $judgingId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['judging_id'])) {
            $args['judging_ids'] = [$args['judging_id']];
        }

        $query = 'SELECT j.judgingid, j.cid, j.submitid, j.result, j.starttime, j.endtime,
                         MAX(jr.runtime) AS maxruntime,
                         (j.endtime IS NULL AND j.valid=0 AND
                          (r.valid IS NULL OR r.valid=0)) AS aborted
                  FROM judging j
                  LEFT JOIN contest c USING (cid)
                  LEFT JOIN submission s USING (submitid)
                  LEFT JOIN judging_run jr USING (judgingid)
                  LEFT JOIN rejudging r ON s.rejudgingid = r.rejudgingid
                  WHERE j.cid = %i';

        // Don't expose judgings of too-late submissions except if
        // explicitly queried for with a specific ID. See comment in
        // submissions endpoint. Same with unconfirmed rejudgings and
        // when verification is required.
        if (!(checkrole('jury') || checkrole('judgehost')) || !isset($args['__primary_key'])) {
            $query .= ' AND s.submittime < c.endtime';
            $query .= ' AND (j.rejudgingid IS NULL OR j.valid = 1)';
            if (dbconfig_get('verification_required', 0)) {
                $query .= ' AND j.verified = 1';
            }
        }

        $result = 0;
        if (array_key_exists('result', $args)) {
            $query .= ' AND result = %s';
            $result = $args['result'];
        } else {
            $query .= ' %_';
            if (!(checkrole('jury') || checkrole('judgehost'))) {
                $query .= ' AND result IS NOT NULL';
            }
        }

        if (! (checkrole('jury') || checkrole('judgehost'))) { // This implies we must be a team
            $query .= ' AND teamid = %i';
            $teamid = $userdata['teamid'];
        } else {
            $query .= ' %_';
            $teamid = 0;
        }

        $hasJudgingids = array_key_exists('judging_ids', $args);
        $query .= ($hasJudgingids ? ' AND judgingid IN (%Ai)' : ' %_');
        $judgingids = ($hasJudgingids ? $args['judging_ids'] : []);

        $hasSubmitid = array_key_exists('submission_id', $args);
        $query .= ($hasSubmitid ? ' AND submitid = %i' : ' %_');
        $submitid = ($hasSubmitid ? $args['submission_id'] : 0);

        $query .= ' GROUP BY j.judgingid ORDER BY j.judgingid';

        $q = $DB->q($query, $cid, $result, $teamid, $judgingids, $submitid);

        $res = array();
        while ($row = $q->next()) {
            $res[] = array(
            'id'                 => safe_string(rest_extid('judgements', $row['judgingid'])),
            'submission_id'      => safe_string(rest_extid('submissions', $row['submitid'])),
            'judgement_type_id'  => empty($row['result']) ? null : $VERDICTS[$row['result']],
            'start_time'         => Utils::absTime($row['starttime']),
            'start_contest_time' => Utils::relTime($row['starttime'] - $cdatas[$row['cid']]['starttime']),
            'end_time'           => empty($row['endtime']) ? null : Utils::absTime($row['endtime']),
            'end_contest_time'   => empty($row['endtime']) ? null : Utils::relTime($row['endtime'] - $cdatas[$row['cid']]['starttime']),
            'max_run_time'       => safe_float($row['maxruntime'], 3),
        );
        }
        return $res;
    }
    $doc = 'Get all or selected judgings. This includes those post-freeze, so currently limited to jury, or as a team but then restricted your own submissions.';
    $args = array('cid' => 'Contest ID. If not provided, get judgings of current contest.',
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

        if (!checkargs($args, array('judgehost'))) {
            return '';
        }

        $host = $args['judgehost'];
        $DB->q('UPDATE judgehost SET polltime = %s WHERE hostname = %s', now(), $host);

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

        $DB->q('UPDATE team SET judging_last_started = %s WHERE teamid = %i',
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
                      ') VALUES(%i,%i,%s,%s' .
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
                        result = "compiler-error", endtime=%s
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

                calcScoreRow($row['cid'], $row['teamid'], $row['probid']);

                // We call alert here for the failed submission. Note that
                // this means that these alert messages should be treated
                // as confidential information.
                alert('reject', "submission $row[submitid], judging $judgingid: compiler-error");
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
                         VALUES (%i, %i, %s, %f, %s, %s, %s, %s, %s)',
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

        $allresults = array_pad($runresults, $numtestcases, null);

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
                $DB->q('UPDATE judging SET result = %s, endtime = %s
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
                calcScoreRow($row['cid'], $row['teamid'], $row['probid']);

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
                        eventlog('judging', $args['judgingid'], 'update', $row['cid']);
                        updateBalloons($row['submitid']);
                    }
                }

                auditlog('judging', $args['judgingid'], 'judged', $result, $args['judgehost']);

                $just_finished = true;
            }
        }

        // Send an event for an endtime update if not done yet.
        if (!isset($jud['rejudgingid']) &&
            count($runresults) == $numtestcases && empty($just_finished)) {
            eventlog('judging', $args['judgingid'], 'update', $jud['cid']);
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
        $onlypublic = !(IS_JURY || checkrole('jury') || checkrole('judgehost'));

        if (isset($args['name'])) {
            return array($args['name'] => dbconfig_get($args['name'], null, false, $onlypublic));
        }

        return dbconfig_get(null, null, false, $onlypublic);
    }
    $doc = 'Get configuration variables.';
    $args = array('name' => 'Search only a single config variable.');
    $exArgs = array(array('name' => 'sourcesize_limit'));
    // Role based (partial) access to configuration variables is handled
    // inside the function.
    $api->provideFunction('GET', 'config', $doc, $args, $exArgs);

    /**
     * Submissions information
     */
    function submissions($args)
    {
        global $DB, $userdata, $cdatas, $cids, $api, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['id'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?id={id}");
                return '';
            }
            $args['ids'] = array_map(function ($submissionId) use ($cid) {
                return rest_intid('submissions', $submissionId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['id'])) {
            $args['ids'] = [$args['id']];
        }

        $query = 'SELECT s.submitid, s.teamid, s.probid, s.langid, s.submittime, s.cid, s.entry_point
                  FROM submission s
                  LEFT JOIN team t USING (teamid)
                  LEFT JOIN team_category c USING (categoryid)
                  WHERE s.valid = 1 AND s.cid = %i';

        // Don't expose too-late submissions except if queried for with a
        // specific ID. This doesn't cause a security risk and is a quick
        // hack to get the eventlog of new submissions to work while not
        // (explicitly) exposing too-late submissions in the API.
        if (isset($args['__primary_key'])) {
            $query .= ' %_';
        } else {
            $query .= ' AND submittime < %i';
        }

        $query .= (checkrole('jury') ? '' : ' AND c.visible = 1');

        $hasLanguage = array_key_exists('language_id', $args);
        $query .= ($hasLanguage ? ' AND s.langid = %s' : ' %_');
        $languageId = ($hasLanguage ? $args['language_id'] : 0);

        $hasSubmitids = array_key_exists('ids', $args);
        $query .= ($hasSubmitids ? ' AND s.submitid IN (%Ai)' : ' %_');
        $submitids = ($hasSubmitids ? $args['ids'] : []);

        $teamid = 0;
        $freezetime = 0;
        if (infreeze($cdatas[$cid], now()) && !checkrole('jury')) {
            $query .= ' AND ( s.submittime < %i';
            $freezetime = $cdatas[$cid]['freezetime'];
            if (checkrole('team')) {
                $query .= ' OR s.teamid = %i';
                $teamid = $userdata['teamid'];
            } else {
                $query .= ' %_';
            }
            $query .= ' )';
        } else {
            $query .= ' %_ %_';
        }

        $query .= ' ORDER BY s.submitid';

        $q = $DB->q($query, $cid, $cdatas[$cid]['endtime'],
                    $languageId, $submitids, $freezetime, $teamid);

        $res = array();
        while ($row = $q->next()) {
            $extcid = safe_string(rest_extid('contests', $cid));
            $extid = safe_string(rest_extid('submissions', $row['submitid']));
            $ret = array(
                'id'           => $extid,
                'team_id'      => safe_string(rest_extid('teams', $row['teamid'])),
                'problem_id'   => safe_string(rest_extid('problems', $row['probid'])),
                'time'         => Utils::absTime($row['submittime']),
                'contest_time' => Utils::relTime($row['submittime'] - $cdatas[$row['cid']]['starttime']),
                'files'        => array(array('href' => "contests/$extcid/submissions/$extid/files")),
            );
            if (checkrole('jury')) {
                $ret['entry_point'] = $row['entry_point'];
                $ret['language_id'] = safe_string(rest_extid('languages', $row['langid']));
            }
            $res[] = $ret;
        }
        return $res;
    }
    $args = array('cid' => 'Contest ID. If not provided, get submissions of current contest.',
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

        $sid = submit_solution($userdata['teamid'], $probid, $cid, $langid, $FILEPATHS, $FILENAMES, null, $entry_point);

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

    /**
     * Judging runs information
     */
    function runs($args)
    {
        global $DB, $cdatas, $cids, $api, $VERDICTS, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['run_id'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?run_id={id}");
                return '';
            }
            $args['run_ids'] = array_map(function ($runId) use ($cid) {
                return rest_intid('runs', $runId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['run_id'])) {
            $args['run_ids'] = [$args['run_id']];
        }

        $query = 'TABLE SELECT jr.runid, jr.judgingid, jr.runresult,
                           jr.endtime, jr.runtime, j.cid, t.rank
                  FROM judging_run jr
                  LEFT JOIN testcase t USING (testcaseid)
                  LEFT JOIN judging j USING (judgingid)
                  LEFT JOIN submission s USING (submitid)
                  LEFT JOIN contest c ON c.cid = j.cid
                  WHERE j.cid = %i';

        // Don't expose judging runs of too-late submissions except if
        // explicitly queried for with a specific ID. See comment in
        // submissions endpoint. Same with unconfirmed rejudgings and
        // when verification is required.
        if (!isset($args['__primary_key'])) {
            $query .= ' AND s.submittime < c.endtime';
            $query .= ' AND (j.rejudgingid IS NULL OR j.valid = 1)';
            if (dbconfig_get('verification_required', 0)) {
                $query .= ' AND j.verified = 1';
            }
        }

        $hasFirstId = array_key_exists('first_id', $args);
        $query .= ($hasFirstId ? ' AND runid >= %i' : ' AND TRUE %_');
        $firstId = ($hasFirstId ? $args['first_id'] : 0);

        $hasLastId = array_key_exists('last_id', $args);
        $query .= ($hasLastId ? ' AND runid <= %i' : ' AND TRUE %_');
        $lastId = ($hasLastId ? $args['last_id'] : 0);

        $hasJudgingid = array_key_exists('judging_id', $args);
        $query .= ($hasJudgingid ? ' AND judgingid = %i' : ' %_');
        $judgingid = ($hasJudgingid ? $args['judging_id'] : 0);

        $hasRunIds = array_key_exists('run_ids', $args);
        $query .= ($hasRunIds ? ' AND runid IN (%Ai)' : ' %_');
        $runid = ($hasRunIds ? $args['run_ids'] : []);

        $hasLimit = array_key_exists('limit', $args);
        $query .= ($hasLimit ? ' LIMIT %i' : ' %_');
        $limit = ($hasLimit ? $args['limit'] : -1);
        // TODO: validate limit

        $runs = $DB->q($query, $cid, $firstId, $lastId, $judgingid, $runid, $limit);
        return array_map(function ($run) use ($VERDICTS, $cdatas) {
            return array(
                'id'                => safe_string(rest_extid('runs', $run['runid'])),
                'judgement_id'      => safe_string(rest_extid('judgements', $run['judgingid'])),
                'ordinal'           => safe_int($run['rank']),
                'judgement_type_id' => safe_string($VERDICTS[$run['runresult']]),
                'time'              => Utils::absTime($run['endtime']),
                'contest_time'      => Utils::relTime($run['endtime'] - $cdatas[$run['cid']]['starttime']),
                'run_time'          => safe_float($run['runtime'], 3),
            );
        }, $runs);
    }
    $doc = 'Get all or selected runs.';
    $args = array('cid' => 'Contest ID. If not provided, get runs in current contest.',
                  'first_id' => 'Search from a certain ID',
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
        return array_map(function ($adata) {
            return array(
                'affilid'   => safe_string(rest_extid('organizations', $adata['affilid'])),
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
        global $DB, $api;

        if (isset($args['__primary_key'])) {
            if (isset($args['affilid'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?affilid={id}");
                return '';
            }
            $args['affilids'] = array_map(function ($organizationId) {
                return rest_intid('organizations', $organizationId);
            }, $args['__primary_key']);
        } elseif (isset($args['affilid'])) {
            $args['affilids'] = [$args['affilid']];
        }

        // Construct query
        $query = 'TABLE SELECT affilid, shortname, name, country FROM team_affiliation WHERE';

        $byCountry = array_key_exists('country', $args);
        $query .= ($byCountry ? ' country = %s' : ' TRUE %_');
        $country = ($byCountry ? $args['country'] : '');

        $byAffilIds = array_key_exists('affilids', $args);
        $query .= ($byAffilIds ? ' AND affilid IN (%Ai)' : ' %_');
        $affilid = ($byAffilIds ? $args['affilids'] : []);

        $query .= ' ORDER BY name';

        $show_flags = dbconfig_get('show_flags', true);

        // Run query and return result
        $adatas = $DB->q($query, $country, $affilid);
        return array_map(function ($adata) use ($args, $show_flags) {
            $ret = array(
                'id'        => safe_string(rest_extid('organizations', $adata['affilid'])),
                'icpc_id'   => safe_string($adata['affilid']),
                'name'      => $adata['name']
            );
            if ($show_flags) {
                $ret['country'] = $adata['country'];
            }
            if (!isset($args['strict'])) {
                $ret['shortname'] = $adata['shortname'];
            }
            return $ret;
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
        global $DB, $api, $cids, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['teamid'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?teamid={id}");
                return '';
            }
            $args['teamids'] = array_map(function ($teamId) use ($cid) {
                return rest_intid('teams', $teamId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['teamid'])) {
            $args['teamids'] = [$args['teamid']];
        }

        // Construct query
        $query = 'TABLE SELECT teamid AS id, t.name, t.members, t.externalid,
                  t.categoryid, t.affilid, a.name AS affiliation, a.country AS nationality,
                  ct.cid
                  FROM team t
                  LEFT JOIN team_affiliation a USING(affilid)
                  LEFT JOIN team_category c USING (categoryid)
                  LEFT JOIN contestteam ct USING (teamid)
                  WHERE t.enabled = 1';

        $public = $DB->q('MAYBEVALUE SELECT public FROM contest WHERE cid = %i', $cid);
        if (!isset($public)) {
            $api->createError("Invalid contest ID '$cid'.");
            return '';
        }
        if (!$public) {
            $query .= ' AND cid IS NOT NULL';
        }

        $byCategory = array_key_exists('category', $args);
        $query .= ($byCategory ? ' AND categoryid = %i' : ' %_');
        $category = ($byCategory ? $args['category'] : 0);

        $byAffil = array_key_exists('affiliation', $args);
        $query .= ($byAffil ? ' AND affilid = %s' : ' %_');
        $affiliation = ($byAffil ? $args['affiliation'] : 0);

        $byTeamids = array_key_exists('teamids', $args);
        $query .= ($byTeamids ? ' AND teamid IN (%Ai)' : ' %_');
        $teamid = ($byTeamids ? $args['teamids'] : []);

        $query .= ($args['public'] ? ' AND visible = 1' : '');

        $show_flags = dbconfig_get('show_flags', true);

        // Run query and return result
        $tdatas = $DB->q($query, $category, $affiliation, $teamid);
        return array_map(function ($tdata) use ($args, $show_flags) {
            $group_ids = array();
            if (isset($tdata['categoryid'])) {
                $group_ids[] = safe_string(rest_extid('groups', $tdata['categoryid']));
            }
            $ret = array(
                'id'              => safe_string(rest_extid('teams', $tdata['id'])),
                'name'            => $tdata['name'],
                'group_ids'       => $group_ids,
                'organization_id' => safe_string(rest_extid('organizations', $tdata['affilid'])),
                'icpc_id'         => $tdata['externalid'],
            );
            if (!isset($args['strict'])) {
                $ret['members']     = $tdata['members'];
                $ret['affiliation'] = $tdata['affiliation'];
                $ret['externalid']  = $tdata['externalid'];
                if ($show_flags) {
                    $ret['nationality'] = $tdata['nationality'];
                }
            }
            return $ret;
        }, $tdatas);
    }
    $args = array('cid' => 'ID of a contest that teams should be part of, defaults to current contest.',
                  'category' => 'ID of a single category/group to search for.',
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
        while ($row = $q->next()) {
            $res[] = array(
                'categoryid' => safe_int($row['categoryid']),
                'name'       => $row['name'],
                'color'      => $row['color'],
                'sortorder'  => safe_int($row['sortorder'])
            );
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
        global $DB, $api;

        $categoryids = [];
        if (isset($args['__primary_key'])) {
            $categoryids = array_map(function ($groupId) {
                return rest_intid('groups', $groupId);
            }, $args['__primary_key']);
        }

        $query = 'SELECT categoryid, name, color, visible, sortorder
                  FROM team_category
                  WHERE TRUE';
        if ($args['public']) {
            $query .= ' AND visible=1';
        }

        $query .= (!empty($categoryids) ? ' AND categoryid IN (%Ai)' : ' %_');

        $q = $DB->q($query . ' ORDER BY sortorder', $categoryids);
        $res = array();
        while ($row = $q->next()) {
            $ret = array(
                'id'         => safe_string(rest_extid('groups', $row['categoryid'])),
                'icpc_id'    => safe_string($row['categoryid']),
                'name'       => safe_string($row['name']),
            );
            if (!$row['visible']) {
                $ret['hidden'] = true;
            }
            if (!isset($args['strict'])) {
                $ret['color']     = $row['color'];
                $ret['sortorder'] = safe_int($row['sortorder']);
            }
            $res[] = $ret;
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
        global $DB, $api;

        if (isset($args['__primary_key'])) {
            if (isset($args['langid'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?langid={id}");
                return '';
            }
            $args['langids'] = array_map(function ($langId) {
                return rest_intid('languages', $langId);
            }, $args['__primary_key']);
        } elseif (isset($args['langid'])) {
            $args['langids'] = [$args['langid']];
        }

        $query = 'SELECT langid, name, extensions, require_entry_point, entry_point_description, allow_judge, time_factor
              FROM language WHERE allow_submit = 1';

        $byLangIds = array_key_exists('langids', $args);
        $query .= ($byLangIds ? ' AND langid IN (%As)' : ' %_');
        $langid = ($byLangIds ? $args['langids'] : []);

        $q = $DB->q($query, $langid);

        $res = array();
        while ($row = $q->next()) {
            $ret = array(
                'id'           => safe_string(rest_extid('languages', $row['langid'])),
                'name'         => safe_string($row['name']),
            );
            if (!isset($args['strict'])) {
                $ret['extensions']  = dj_json_decode($row['extensions']);
                $ret['require_entry_point'] = safe_bool($row['require_entry_point']);
                $ret['entry_point_description'] = safe_string($row['entry_point_description']);
                $ret['allow_judge'] = safe_bool($row['allow_judge']);
                $ret['time_factor'] = safe_float($row['time_factor']);
            }
            $res[] = $ret;
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
        global $cids, $cdatas, $DB, $api, $requestedCid;

        if (isset($args['cid'])) {
            $cid = safe_int($args['cid']);
        } elseif (isset($requestedCid)) {
            $cid = $requestedCid;
        } else {
            if (count($cids)>=1) {
                $cid = reset($cids);
            } else {
                $api->createError("No active contest found.", NOT_FOUND);
                return '';
            }
        }

        if (isset($args['__primary_key'])) {
            if (isset($args['clar_id'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?clar_id={id}");
                return '';
            }
            $args['clar_ids'] = array_map(function ($clarId) use ($cid) {
                return rest_intid('clarifications', $clarId, $cid);
            }, $args['__primary_key']);
        } elseif (isset($args['clar_id'])) {
            $args['clar_ids'] = [$args['clar_id']];
        }

        // Find clarifications, maybe later also provide more info for jury
        $query = 'TABLE SELECT clarid, submittime, probid, body, cid, sender, recipient, respid
                  FROM clarification
                  WHERE cid = %i';

        $byProblem = array_key_exists('problem', $args);
        $query .= ($byProblem ? ' AND probid = %i' : ' %_');
        $problem = ($byProblem ? $args['problem'] : null);

        $byClarIds = array_key_exists('clar_ids', $args);
        $query .= ($byClarIds ? ' AND clarid IN (%Ai)' : ' %_');
        $clarId = ($byClarIds ? $args['clar_ids'] : []);

        $clar_datas = $DB->q($query, $cid, $problem, $clarId);
        return array_map(function ($clar_data) use ($cdatas) {
            return array(
                'id'           => safe_string(rest_extid('clarifications', $clar_data['clarid'])),
                'time'         => Utils::absTime($clar_data['submittime']),
                'contest_time' => Utils::relTime($clar_data['submittime'] - $cdatas[$clar_data['cid']]['starttime']),
                'problem_id'   => safe_string(rest_extid('problems', $clar_data['probid'])),
                'from_team_id' => safe_string(rest_extid('teams', $clar_data['sender'])),
                'to_team_id'   => safe_string(rest_extid('teams', $clar_data['recipient'])),
                'reply_to_id'  => safe_string(rest_extid('clarifications', $clar_data['respid'])),
                'text'         => $clar_data['body'],
            );
        }, $clar_datas);
    }
    $doc = 'Get a list of clarifications.';
    $args = array('cid' => 'Search clarifications for a specific contest, defaults to current contest.',
                  'problem' => 'Search for clarifications about a specific problem.');
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
        return array_map(function ($jdata) {
            return array(
                'hostname' => $jdata['hostname'],
                'active'   => safe_bool($jdata['active']),
                'polltime' => safe_float($jdata['polltime'], 3),
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
                    $prob['time'] = scoretime($pdata['time']);
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
                           VALUES (%i, %i, %s, %s, %i, %s)',
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

    function judgement_types($args)
    {
        global $VERDICTS, $api;

        if (isset($args['__primary_key'])) {
            if (isset($args['verdict'])) {
                $api->createError("You cannot specify a primary ID both via /{id} and ?verdict={id}");
                return '';
            }
            $args['verdicts'] = $args['__primary_key'];
        } elseif (isset($args['verdict'])) {
            $args['verdicts'] = [$args['verdict']];
        }

        $res = array();
        foreach ($VERDICTS as $name => $label) {
            $penalty = true;
            $solved = false;
            if ($name == 'correct') {
                $penalty = false;
                $solved = true;
            }
            if ($name == 'compiler-error') {
                $penalty = dbconfig_get('compile_penalty', false);
            }
            if (isset($args['verdicts']) && !in_array($label, $args['verdicts'])) {
                continue;
            }
            $res[] = array(
                'id'      => safe_string($label),
                'name'    => str_replace('-', ' ', $name),
                'penalty' => safe_bool($penalty),
                'solved'  => safe_bool($solved),
            );
        }

        return $res;
    }
    $doc = 'Lists all available judgement types.';
    $args = array();
    $exArgs = array();
    $api->provideFunction('GET', 'judgement_types', $doc, $args, $exArgs, null, true);
}

// Now provide the api, which will handle the request
$api->provideApi(true);
