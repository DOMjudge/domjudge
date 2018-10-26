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

    $api = new RestApi();

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
}

// Now provide the api, which will handle the request
$api->provideApi(true);
