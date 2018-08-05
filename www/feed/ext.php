<?php
/**
 * Output scoreboard in XML format for ICPC scoreboard
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

require(LIBWWWDIR . '/scoreboard.php');

if (count($cdatas) != 1) {
    error("Feed only supports exactly one active contest.");
} else {
    $cdata = reset($cdatas);
    $cid   = reset($cids);
}


/**
 * DOM XML tree helper functions (PHP 5).
 * The XML tree is assumed to be named '$xmldoc' and the XPath object '$xpath'.
 */

/**
 * Create node and add below $paren.
 * $value is an optional element value and $attrs an array whose
 * key,value pairs are added as node attributes. All strings are specialchars
 */
function XMLaddnode($paren, $name, $value = null, $attrs = null)
{
    global $xmldoc;

    if ($value === null) {
        $node = $xmldoc->createElement(specialchars($name, ENT_XML1));
    } else {
        $node = $xmldoc->createElement(
            specialchars($name, ENT_XML1),
                                       specialchars($value, ENT_XML1)
        );
    }

    if (count($attrs) > 0) {
        foreach ($attrs as $key => $value) {
            $node->setAttribute(
                specialchars($key, ENT_XML1),
                                specialchars($value, ENT_XML1)
            );
        }
    }

    $paren->appendChild($node);
    return $node;
}

/**
 * Retrieve node by a path from root, or relative to paren if non-null.
 * Generates error if no or more than one nodes are found.
 */
function XMLgetnode($path, $paren = null)
{
    global $xpath;

    $nodelist = $xpath->query($path, $paren);

    if ($nodelist->length!=1) {
        error("Not exactly one XML node found");
    }

    return $nodelist->item(0);
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

/**
 * Formats a floating point timestamp by truncating it to milliseconds.
 */
function formattime($time)
{
    return safe_float($time, 8);
}

// Get problems, languages, affiliations, categories and events
$probs = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, name, color, shortname
                 FROM problem INNER JOIN contestproblem USING(probid)
                 WHERE cid = %i AND allow_submit = 1 ORDER BY shortname', $cid);

$langs = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name FROM language
                 WHERE allow_submit = 1 ORDER BY langid');

$teams = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, name, externalid, affilid, categoryid
                 FROM team WHERE enabled = 1 ORDER BY teamid');

$affils = $DB->q('KEYTABLE SELECT affilid AS ARRAYKEY, name, country, shortname
                  FROM team_affiliation ORDER BY name');

$categs = $DB->q('KEYTABLE SELECT categoryid AS ARRAYKEY, name, color
                  FROM team_category WHERE visible = 1 ORDER BY categoryid');

$clars = $DB->q('SELECT t.clarid, t.submittime, t.sender, j.recipient, t.probid,
                 t.body AS question, j.body AS answer
                 FROM clarification t
                 LEFT JOIN clarification j ON (t.clarid = j.respid)
                 WHERE t.sender IS NOT NULL');

$events = $DB->q('SELECT * FROM event WHERE cid = %i AND ' .
                 (isset($_REQUEST['fromid']) ? 'eventid >= %i ' : 'TRUE %_ ') . 'AND ' .
                 (isset($_REQUEST['toid'])   ? 'eventid <  %i ' : 'TRUE %_ ') .
                 'ORDER BY eventid', $cid, (int)@$_REQUEST['fromid'], (int)@$_REQUEST['toid']);

$xmldoc = new DOMDocument('1.0', DJ_CHARACTER_SET);

$root       = XMLaddnode($xmldoc, 'contest');
$reset      = XMLaddnode($root, 'reset');
$info       = XMLaddnode($root, 'info');

$fdata = calcFreezeData($cdata);

// write out general info
$length = calcContestTime($cdata['endtime'], $cid);
$lengthString = sprintf('%02d:%02d:%02d', $length/(60*60), ($length/60) % 60, $length % 60);
if (isset($cdata['freezetime'])) {
    $freezelength = calcContestTime($cdata['endtime'], $cid)
                   -calcContestTime($cdata['freezetime'], $cid);
} else {
    $freezelength = 0;
}
$freezelengthString = sprintf('%02d:%02d:%02d', $freezelength/(60*60), ($freezelength/60) % 60, $freezelength % 60);

XMLaddnode($info, 'contest-id', $cdata['externalid']);
XMLaddnode($info, 'length', $lengthString);
XMLaddnode($info, 'scoreboard-freeze-length', $freezelengthString);
XMLaddnode($info, 'penalty', dbconfig_get('penalty_time', 20));
XMLaddnode($info, 'started', $fdata['cstarted'] ? 'True' : 'False');
XMLaddnode($info, 'starttime', $cdata['starttime_enabled'] ? formattime($cdata['starttime']) : 'undefined');
XMLaddnode($info, 'title', $cdata['name']);
XMLaddnode($info, 'short-title', $cdata['shortname']);

// write out languages
$id_cnt = 0;
foreach ($langs as $lang => $data) {
    $id_cnt++;
    $lang_to_id[$lang] = $id_cnt;
    $node = XMLaddnode($root, 'language');
    XMLaddnode($node, 'id', $id_cnt);
    XMLaddnode($node, 'name', $data['name']);
}

// write out regions
foreach ($categs as $region => $data) {
    $node = XMLaddnode($root, 'region');
    XMLaddnode($node, 'external-id', $region);
    XMLaddnode($node, 'name', $data['name']);
}

// write out possible verdicts
foreach ($VERDICTS as $long_verdict => $acronym) {
    $node = XMLaddnode($root, 'judgement');
    XMLaddnode($node, 'acronym', $acronym);
    XMLaddnode($node, 'name', $long_verdict);
    XMLaddnode($node, 'solved', $long_verdict==='correct' ? 'True' : 'False');
    $penalty = true;
    if ($long_verdict==='correct' ||
        ($long_verdict==='compiler-error' &&
         $compile_penalty==0)) {
        $penalty = false;
    }
    XMLaddnode($node, 'penalty', $penalty ? 'True' : 'False');
}


// write out problems
$id_cnt = 0;
foreach ($probs as $prob => $data) {
    $id_cnt++;
    $prob_to_id[$prob] = $id_cnt;
    $node = XMLaddnode($root, 'problem');
    XMLaddnode($node, 'id', $id_cnt);
    XMLaddnode($node, 'label', $data['shortname']);
    XMLaddnode($node, 'name', $data['name']);
    if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $data['color'])) {
        XMLaddnode($node, 'rgb', substr($data['color'], 1));
    } else {
        XMLaddnode($node, 'color', $data['color']);
    }
}

// write out teams
$id_cnt = 0;
foreach ($teams as $team => $data) {
    if (!isset($categs[$data['categoryid']])) {
        continue;
    }
    $id_cnt++;
    $team_to_id[$team] = $id_cnt;
    $node = XMLaddnode($root, 'team');
    XMLaddnode($node, 'id', $id_cnt);
    XMLaddnode($node, 'name', $data['name']);
    if (isset($data['externalid'])) {
        XMLaddnode($node, 'external-id', $data['externalid']);
    }
    if (isset($data['affilid'])) {
        XMLaddnode($node, 'nationality', $affils[$data['affilid']]['country']);
        XMLaddnode($node, 'university', $affils[$data['affilid']]['name']);
    }
    XMLaddnode($node, 'region', $categs[$data['categoryid']]['name']);
}

// write out clars
while ($row = $clars->next()) {
    $node = XMLaddnode($root, 'clar');
    XMLaddnode($node, 'id', $row['clarid']);
    XMLaddnode($node, 'team', $team_to_id[$row['sender']]);
    XMLaddnode($node, 'problem', $prob_to_id[$row['probid']]);
    XMLaddnode($node, 'time', formattime(calcContestTime($row['submittime'], $cid)));
    XMLaddnode($node, 'timestamp', formattime($row['submittime']));
    XMLaddnode($node, 'question', $row['question']);
    if (isset($row['answer'])) {
        XMLaddnode($node, 'answer', $row['answer']);
        XMLaddnode($node, 'answered', 'True');
        XMLaddnode($node, 'to-all', isset($row['recipient']) ? 'False' : 'True');
    } else {
        XMLaddnode($node, 'answered', 'False');
    }
}

$compile_penalty = dbconfig_get('compile_penalty', 0);

// write out runs
while ($row = $events->next()) {
    if (!in_array($row['endpointtype'], array('submissions', 'judgements'), true)) {
        continue;
    }

    if (empty($row['content'])) {
        error("Missing JSON data for event ID ".$row['eventid'].
              " endpoint ".$row['endpointtype'].'/'.$row['endpointid']);
    }
    $eventdata = dj_json_decode($row['content']);

    $submitid = $row['endpointtype']=='submissions' ? $eventdata['id'] : $eventdata['submission_id'];

    $data = $DB->q('MAYBETUPLE SELECT submittime, teamid, probid, name AS langname, valid
                    FROM submission
                    LEFT JOIN language USING (langid)
                    WHERE valid = 1 AND submitid = %i',
                   $submitid);

    if (empty($data) ||
        difftime($data['submittime'], $cdata['endtime'])>=0 ||
        !isset($prob_to_id[$data['probid']]) ||
        !isset($team_to_id[$data['teamid']])) {
        continue;
    }

    $run = XMLaddnode($root, 'run');
    XMLaddnode($run, 'id', $submitid);
    XMLaddnode($run, 'problem', $prob_to_id[$data['probid']]);
    XMLaddnode($run, 'team', $team_to_id[$data['teamid']]);
    XMLaddnode($run, 'timestamp', formattime($row['eventtime']));
    XMLaddnode($run, 'time', formattime(calcContestTime($data['submittime'], $cid)));
    XMLaddnode($run, 'language', $data['langname']);

    if ($row['endpointtype'] == 'submissions') {
        XMLaddnode($run, 'judged', 'False');
        XMLaddnode($run, 'status', 'fresh');
    } else { // judgements
        $jdata = $DB->q('MAYBETUPLE SELECT result, starttime FROM judging j
                         LEFT JOIN submission USING(submitid)
                         WHERE j.valid = 1 AND judgingid = %i', $row['dataid']);

        if (!isset($jdata['result'])) {
            continue;
        }

        $ntestcases = $DB->q('VALUE SELECT count(*) FROM testcase
                              WHERE probid = %i', $data['probid']);

        $jruns = $DB->q('SELECT rank, runresult, runtime
                         FROM judging_run
                         LEFT JOIN testcase USING (testcaseid)
                         WHERE runresult IS NOT NULL AND judgingid = %i',
                        $row['dataid']
        );

        // We don't store single judging_run timestamps, so calculate
        // these cumulatively from judging starttime.
        $timestamp = (float)$jdata['starttime'];

        while ($jrun = $jruns->next()) {
            $testcase = XMLaddnode($root, 'testcase');
            XMLaddnode($testcase, 'i', $jrun['rank']);
            XMLaddnode($testcase, 'judged', 'True');
            XMLaddnode($testcase, 'judgement', $VERDICTS[$jrun['runresult']]);
            XMLaddnode($testcase, 'n', $ntestcases);
            XMLaddnode($testcase, 'run-id', $submitid);
            XMLaddnode($testcase, 'solved', ($jrun['runresult']=='correct' ? 'True' : 'False'));
            XMLaddnode($testcase, 'time', formattime($jrun['runtime']));
            XMLaddnode($testcase, 'timestamp', formattime($timestamp));
            $timestamp += (float)$jrun['runtime'];
        }

        XMLaddnode($run, 'judged', 'True');
        XMLaddnode($run, 'status', 'done');
        XMLaddnode($run, 'judging-time', formattime(calcContestTime($jdata['starttime'], $cid)));
        XMLaddnode($run, 'judging-timestamp', formattime($jdata['starttime']));
        XMLaddnode($run, 'result', $VERDICTS[$jdata['result']]);
        if ($jdata['result'] == 'correct') {
            XMLaddnode($run, 'solved', 'True');
            XMLaddnode($run, 'penalty', 'False');
        } else {
            XMLaddnode($run, 'solved', 'False');
            if ($compile_penalty == 0 && $jdata['result'] == 'compiler-error') {
                XMLaddnode($run, 'penalty', 'False');
            } else {
                XMLaddnode($run, 'penalty', 'True');
            }
        }
    }
}

if (isset($cdata['finalizetime'])) {
    $node = XMLaddnode($root, 'finalized');
    XMLaddnode($node, 'timestamp', $cdata['finalizetime']);
    // FIXME: b is not in master (yet). But perhaps it doesn't even make sense to bother with the old feed.
    XMLaddnode($node, 'last-gold', 0);
    XMLaddnode($node, 'last-silver', 0);
    XMLaddnode($node, 'last-bronze', 0);
    XMLaddnode($node, 'comment', $cdata['finalizecomment']);
}

header('Content-Type: text/xml; charset=' . DJ_CHARACTER_SET);

$xmldoc->formatOutput = true;
echo $xmldoc->saveXML();
