<?php
/**
 * Do various sanity checks on the system regarding data constraints,
 * permissions and the like. At the moment this only contains some basic
 * checks but this can be extended in the future.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Config Checker';
require(LIBWWWDIR . '/header.php');

requireAdmin();

// Turn off output buffering, to see the page as it (slowly) loads.
ob_end_flush();

$time_start = microtime(true);

?>

<h1>Config Checker</h1>

<?php

/** Print the output of phpinfo(), which may be useful to check which settings
 *  PHP is actually using. */
if ($_SERVER['QUERY_STRING'] == 'phpinfo') {
    $ret = "<p><a href=\"./checkconfig.php\">return to config checker</a></p>\n\n";
    echo $ret;
    echo "<h2>PHP Information</h2>\n\n";
    phpinfo();
    echo $ret;
    require(LIBWWWDIR . '/footer.php');
    exit;
}

require_once(LIBWWWDIR . '/checkers.jury.php');


$RESULTS = array();

function result($section, $item, $result, $details, $details_html = '')
{
    global $RESULTS;

    $RESULTS[] = array(
        'section' => $section,
        'item' => $item,
        'result' => $result,
        'details' => $details,
        'details_html' => $details_html,
        'flushed' => false);
}

$lastsection = false; $resultno = 0;

function flushresults()
{
    global $RESULTS, $lastsection, $resultno;

    foreach ($RESULTS as &$row) {
        if ($row['flushed']) {
            continue;
        }
        $row['flushed'] = true;

        if (empty($row['details']) && empty($row['details_html'])) {
            $row['details'] = 'No issues found.';
        }

        if ($row['section'] != $lastsection) {
            echo "<tr><th colspan=\"2\">" .
                specialchars(ucfirst($row['section'])) .
                "</th></tr>\n";
            $lastsection = $row['section'];
        }

        echo "<tr class=\"result " . specialchars($row['result']) .
            "\"><td class=\"resulticon\"><img src=\"../images/s_";
        switch ($row['result']) {
        case 'O': echo "okay"; break;
        case 'W': echo "warn"; break;
        case 'E': echo "error"; break;
        case 'R': echo "refint"; break;
        default: error("Unknown config checker result: ".$row['result']);
        }
        echo ".png\" alt=\"" . $row['result'] . "\" class=\"picto\" /></td><td>" .
            specialchars($row['item']) ." " .
            "<a href=\"javascript:collapse($resultno)\"><img src=\"../images/b_help.png\" " .
            "alt=\"?\" title=\"show details\" class=\"smallpicto helpicon\" /></a>\n" .
            "<div class=\"details\" id=\"detail$resultno\">" .
            nl2br(specialchars(trim($row['details']))."\n") . $row['details_html'] .
            "</div></td></tr>\n";

        ++$resultno;
    }

    flush();
}

echo "<table class=\"configcheck\">\n";

// SOFTWARE

if (!function_exists('version_compare') || version_compare('7.0', PHP_VERSION, '>=')) {
    result(
        'software',
        'PHP version',
        'E',
        'You have PHP ' . PHP_VERSION . ', but need at least 7.0.0.',
        'See <a href="?phpinfo">phpinfo</a> for details.'
    );
} else {
    result(
        'software',
        'PHP version',
        'O',
        'You have PHP ' . PHP_VERSION . '.',
        'See <a href="?phpinfo">phpinfo</a> for details.'
    );
}

if (!function_exists('gd_info')) {
    result(
        'software',
        'PHP GD library',
        'W',
           'The PHP GD library is not available. Test case images cannot be uploaded.'
    );
} else {
    result(
        'software',
        'PHP GD library',
        'O',
           'The PHP GD library is available to handle test case images.'
    );
}

if (extension_loaded('suhosin')) {
    result(
        'software',
        'suhosin',
        'E',
           'PHP suhosin extension loaded. This may result in dropping POST arguments, e.g. output_run.'
    );
} else {
    result('software', 'suhosin', 'O', 'PHP suhosin extension disabled.');
}

$max_file_check = max(100, dbconfig_get('sourcefiles_limit', 100));
result(
    'software',
    'PHP max_file_uploads',
       (int) ini_get('max_file_uploads') < $max_file_check ? 'W':'O',
       'PHP max_file_uploads is set to ' .
       (int) ini_get('max_file_uploads') . '. This should be set higher ' .
       'than the maximum number of test cases per problem and the ' .
       'configuration setting \'sourcefiles_limit\'.'
);


$sizes = array();
$postmaxvars = array('post_max_size', 'memory_limit', 'upload_max_filesize');
foreach ($postmaxvars as $var) {
    /* skip 0 or empty values, and -1 which means 'unlimited' */
    if ($size = phpini_to_bytes(ini_get($var))) {
        if ($size != '-1') {
            $sizes[$var] = $size;
        }
    }
}

$resulttext = 'PHP POST/upload filesize is limited to ' . printsize(min($sizes)) .
    "\n\nThis limit needs to be larger than the testcases you want to upload and than the amount of program output you expect the judgedaemons to post back to DOMjudge. We recommend at least 50 MB.\n\nNote that you need to ensure that all of the following php.ini parameters are at minimum the desired size:\n";
foreach ($postmaxvars as $var) {
    $resulttext .= "$var (now set to " .
        (isset($sizes[$var]) ? printsize($sizes[$var]) : "unlimited") .
        ")\n";
}

result(
    'software',
    'PHP POST/upload filesize',
       min($sizes) < 52428800 ? 'W':'O',
    '',
    $resulttext
);

$timezone_php = ini_get('date.timezone');
$timezone_sys = date_default_timezone_get();
if ($timezone_php===false || empty($timezone_php)) {
    if (empty($timezone_sys) || $timezone_sys=='UTC') {
        result(
            'software',
            'PHP timezone',
            'E',
               "date.timezone is unset in php.ini and the system default '" .
               $timezone_sys . "' may not be properly detected."
        );
    } else {
        result(
            'software',
            'PHP timezone',
            'W',
               "date.timezone is unset in php.ini, PHP is " .
               "using the system default '$timezone_sys'."
        );
    }
} else {
    result('software', 'PHP timezone', 'O', "date.timezone set to '$timezone_php'.");
}

if (class_exists("ZipArchive")) {
    result(
        'software',
        'Problem up/download via zip bundles',
           'O',
        'PHP ZipArchive class available for importing and exporting problem data.'
    );
} else {
    result(
        'software',
        'Problem up/download via zip bundles',
           'W',
        'Optionally, enable the PHP zip extension ' .
           'to be able to import or export problem data via zip bundles.'
    );
}

$mysqldata = array();
$mysqldatares = $DB->q('SHOW variables WHERE Variable_name IN
                        ("innodb_log_file_size", "max_connections",
                         "max_allowed_packet", "tx_isolation", "version")');
while ($row = $mysqldatares->next()) {
    $mysqldata[$row['Variable_name']] = $row['Value'];
}

result(
    'software',
    'MySQL version',
    version_compare('5.5.3', $mysqldata['version'], '>=') ? 'E':'O',
    'Connected to MySQL server version ' . $mysqldata['version'] .
    '. Minimum required is 5.5.3.'
);

result(
    'software',
    'MySQL maximum connections',
    $mysqldata['max_connections'] < 300 ? 'W':'O',
    'MySQL\'s max_connections is set to ' .
    (int)$mysqldata['max_connections'] . '. In our experience ' .
    'you need at least 300, but better 1000 connections to ' .
    'prevent connection refusal during the contest.'
);

result(
    'software',
    'MySQL maximum packet size',
    $mysqldata['max_allowed_packet'] < 16*1024*1024 ? 'W':'O',
    'MySQL\'s max_allowed_packet is set to ' .
    printsize($mysqldata['max_allowed_packet']) . '. You may ' .
    'want to raise this to about twice the maximum test case size.'
);

result(
    'software',
    'MySQL innodb logfile size',
    $mysqldata['innodb_log_file_size'] < 128*1024*1024 ? 'W':'O',
    'MySQL\'s innodb_log_file_size is set to ' .
    printsize($mysqldata['innodb_log_file_size']) . '. You may ' .
    'want to raise this to 10x the maximum test case size.'
);

result(
    'software',
    'MySQL transaction isolation level',
    in_array($mysqldata['tx_isolation'], array('REPEATABLE-READ','SERIALIZABLE')) ? 'O':'W',
    'MySQL\'s transaction isolation level is set to ' . $mysqldata['tx_isolation'] .
    '. You should set this to REPEATABLE-READ or SERIALIZABLE to ' .
    'prevent data inconsistencies.'
);

flushresults();

// CONFIGURATION

if ($DB->q('VALUE SELECT count(*) FROM user
             WHERE username = "admin" AND password=MD5("admin#admin")') != 0) {
    result(
        'configuration',
        'Default admin password',
        'E',
        'The "admin" user still has the default password. You should change it immediately.'
    );
} else {
    result(
        'configuration',
        'Default admin password',
        'O',
        'Password for "admin" has been changed from the default.'
    );
}

foreach (array('compare', 'run') as $type) {
    if ($DB->q('VALUE SELECT count(*) FROM executable WHERE execid = %s',
               dbconfig_get('default_' . $type)) == 0) {
        result(
            'configuration',
            'Default ' . $type .' script',
            'E',
            'The default ' . $type . ' script "' .
            dbconfig_get('default_' . $type) . '" does not exist.'
        );
    }
}

result(
    'configuration',
    'Compile file size vs. memory limit',
    (dbconfig_get('script_filesize_limit')<dbconfig_get('memory_limit') ? 'W' : 'O'),
    'If the script filesize limit is lower than the memory limit, then ' .
    'compilation of sources that statically allocate memory may fail.'
);

if (DEBUG == 0) {
    result('configuration', 'Debugging', 'O', 'Debugging disabled.');
} else {
    result(
        'configuration',
        'Debugging',
        'W',
        'Debug information enabled (level ' . DEBUG .").\n" .
        'Should not be enabled on live systems.'
    );
}

if (!is_writable(TMPDIR)) {
    result(
        'configuration',
        'TMPDIR writable',
        'W',
        'TMPDIR (' . TMPDIR . ') is not writable by the webserver; ' .
        'Showing diffs and editing of submissions may not work.'
    );
} else {
    result(
        'configuration',
        'TMPDIR writable',
        'O',
        'TMPDIR (' . TMPDIR . ') can be used to store temporary ' .
        'files for submission diffs and edits.'
    );
}

flushresults();

// CONTESTS

if (empty($cids)) {
    result(
        'contests',
        'Active contests',
        'E',
        'No currently active contests found. System will not function.'
    );
} else {
    $cidstring = implode(', ', array_map(function ($cid) {
        return 'c'.$cid;
    }, $cids));
    result(
        'contests',
        'Active contests',
        'O',
        'Currently active contests: ' . $cidstring
    );
}

// get all contests
$res = $DB->q('SELECT * FROM contest ORDER BY cid');

$detail = '';
$has_errors = false;
while ($cdata = $res->next()) {
    $cp = $DB->q('SELECT * FROM contestproblem
                  WHERE cid = %i ORDER BY shortname', $cdata['cid']);

    $detail .=  "c".(int)$cdata['cid'].": ";

    $CHECKER_ERRORS = array();
    check_contest($cdata, array('cid' => $cdata['cid']));
    while ($cpdata = $cp->next()) {
        check_contestproblem($cpdata, array('cid' => $cpdata['cid'], 'probid' => $cpdata['probid']));
    }
    if (count($CHECKER_ERRORS) > 0) {
        foreach ($CHECKER_ERRORS as $chk_err) {
            $detail .= $chk_err . "\n";
            $has_errors = true;
        }
    } else {
        $detail .= "OK";
    }

    $detail .= "\n";
}

result(
    'contests',
    'Contests integrity',
    $has_errors ? 'E' : 'O',
    $detail
);

flushresults();

// PROBLEMS

$problems = $DB->q('KEYTABLE SELECT probid AS ARRAYKEY, probid, cid, shortname,
                        timelimit, outputlimit, special_compare, special_run
                    FROM problem INNER JOIN contestproblem USING (probid)
                    ORDER BY probid');


// Select all active judgehosts including restrictions, so we can
// check for all problem,language pairs whether they are judgeable.
$judgehosts = $DB->q('TABLE SELECT hostname, restrictionid FROM judgehost
                      WHERE active = 1 ORDER BY hostname');
$judgehost_without_restrictions = false;
foreach ($judgehosts as &$judgehost) {
    if ($judgehost['restrictionid'] === null) {
        $judgehost_without_restrictions = true;
        break;
    }

    // Get judgehost restrictions
    $judgehost['contests'] = array();
    $judgehost['problems'] = array();
    $judgehost['languages'] = array();
    $restrictions = $DB->q('MAYBEVALUE SELECT restrictions FROM judgehost
                            INNER JOIN judgehost_restriction USING (restrictionid)
                            WHERE hostname = %s ORDER BY restrictionid',
                           $judgehost['hostname']
    );
    if ($restrictions) {
        $restrictions = dj_json_decode($restrictions);
        $judgehost['contests'] = @$restrictions['contest'];
        $judgehost['problems'] = @$restrictions['problem'];
        $judgehost['languages'] = @$restrictions['language'];
    }

    $extra_where = '';
    if (empty($judgehost['contests'])) {
        $extra_where .= '%_ ';
    } else {
        $extra_where .= 'AND cp.cid IN (%Ai) ';
    }

    if (empty($judgehost['problems'])) {
        $extra_where .= '%_ ';
    } else {
        $extra_where .= 'AND cp.probid IN (%Ai) ';
    }

    if (empty($judgehost['languages'])) {
        $extra_where .= '%_ ';
    } else {
        $extra_where .= 'AND l.langid IN (%As) ';
    }

    $judgehost['extra_where'] = $extra_where;

    unset($judgehost);
}

$languages = $DB->q("KEYVALUETABLE SELECT langid, name FROM language
                     WHERE allow_submit = 1 AND allow_judge = 1
                     ORDER BY langid");

$details = '';
$details_html = '';
foreach ($problems as $prob) {
    $CHECKER_ERRORS = array();
    check_problem($prob);
    if (count($CHECKER_ERRORS) > 0) {
        foreach ($CHECKER_ERRORS as $chk_err) {
            $details .= 'p'.$prob['probid']." in contest c" . $prob['cid'] .': ' . $chk_err."\n";
        }
    }
    if (! $DB->q('MAYBEVALUE SELECT count(testcaseid) FROM testcase
                  WHERE input IS NOT NULL AND output IS NOT NULL AND
                  probid = %i', $prob['probid'])) {
        $details .= 'p'.$prob['probid']." in contest c" . $prob['cid'] . ": missing in/output testcase.\n";
    }

    // Check for each problem,language pair if this can be judged by a judgehost.
    foreach ($languages as $langid => $langname) {
        $language_ok = $judgehost_without_restrictions;
        foreach ($judgehosts as $judgehost) {
            if ($language_ok) {
                break;
            }
            $found = $DB->q('MAYBEVALUE SELECT cp.probid
                             FROM contestproblem cp, language l
                             WHERE cp.probid = %i AND cp.cid = %i AND l.langid = %s' .
                            $judgehost['extra_where'],
                            $prob['probid'], $prob['cid'], $langid,
                            $judgehost['contests'], $judgehost['problems'], $judgehost['languages']);
            if ($found) {
                $language_ok = true;
            }
        }

        if (!$language_ok) {
            $details .= 'p'.$prob['probid']." in contest c" . $prob['cid'] . ": no judgehost can judge for language " . $langname . ".\n";
        }
    }

    // Check testcase md5sum and size
    foreach (array('input','output') as $inout) {
        $mismatch = $DB->q("SELECT probid, rank FROM testcase
                            WHERE md5($inout) != md5sum_$inout
                            ORDER BY probid, rank");
        while ($r = $mismatch->next()) {
            $details .= 'p'.$r['probid'] . ": testcase #" . $r['rank'] .
                     " MD5 sum mismatch between $inout and md5sum_$inout\n";
        }
    }
    $outputlimit = 1024*(isset($prob['outputlimit']) ? $prob['outputlimit'] : dbconfig_get('output_limit'));
    $oversize = $DB->q("SELECT rank, OCTET_LENGTH(output) AS size
                        FROM testcase
                        WHERE probid = %i AND OCTET_LENGTH(output) > %i
                        ORDER BY rank",
                       $prob['probid'],
        $outputlimit
    );
    while ($r = $oversize->next()) {
        $details_html .= 'p'.$prob['probid'] . ": testcase #" . $r['rank'] .
                         " output size (" . printsize($r['size']) .
                         ") exceeds output_limit<br />\n";
    }
}

$has_errors = ($details != '' || $details_html != '');
$probs = $DB->q("TABLE SELECT probid, cid FROM contestproblem
                 WHERE color IS NULL ORDER BY probid");
foreach ($probs as $probdata) {
    $details .= 'p'.$probdata['probid'] . " in contest c" . $probdata['cid'] . ": has no colour\n";
}
$probs = $DB->q('TABLE SELECT probid, cid, memlimit
                 FROM problem INNER JOIN contestproblem USING (probid)
                 WHERE memlimit IS NOT NULL
                 ORDER BY probid');
foreach ($probs as $probdata) {
    if ($probdata['memlimit']>dbconfig_get('script_filesize_limit')) {
        $details .= 'p'.$probdata['probid']." in contest c" . $probdata['cid'] .
                 ': memory limit ' . $probdata['memlimit'] .
                 " is larger than script filesize limit.\n";
    }
}

result(
    'problems, languages, teams',
    'Problems integrity',
    ($details == '' && $details_html == '') ? 'O':($has_errors?'E':'W'),
    $details,
    $details_html
);

flushresults();

// LANGUAGES

$res = $DB->q('SELECT * FROM language ORDER BY langid');

$details = ''; $langseverity = 'W';
while ($row = $res->next()) {
    $CHECKER_ERRORS = array();
    check_language($row);
    if (count($CHECKER_ERRORS) > 0) {
        foreach ($CHECKER_ERRORS as $chk_err) {
            $details .= $row['langid'].': ' . $chk_err;
            // if this language is set to 'submittable', it's an error
            if ($row['allow_submit'] == 1) {
                $langseverity = 'E';
            } else {
                $details .= ' (but is not submittable)';
            }
            $details .= "\n";
        }
    }
}

result(
    'problems, languages, teams',
    'Languages integrity',
    $details == '' ? 'O': $langseverity,
    $details
);

$details = '';
if (dbconfig_get('show_affiliations', 1)) {
    $res = $DB->q('SELECT affilid FROM team_affiliation ORDER BY affilid');

    while ($row = $res->next()) {
        $CHECKER_ERRORS = array();
        check_affiliation($row);
        if (count($CHECKER_ERRORS) > 0) {
            foreach ($CHECKER_ERRORS as $chk_err) {
                $details .= $row['affilid'].': ' . $chk_err . "\n";
            }
        }
    }

    $res = $DB->q('SELECT DISTINCT country FROM team_affiliation
                   WHERE country IS NOT NULL ORDER BY country');
    while ($row = $res->next()) {
        $cflag = WEBAPPDIR . '/web/images/countries/' .
            urlencode($row['country']) . '.png';
        if (! file_exists($cflag)) {
            $details .= "Country " . $row['country'] .
                " does not have a flag (looking for $cflag).\n";
        } elseif (! is_readable($cflag)) {
            $details .= "Country " . $row['country'] .
                " has a flag, but it's not readable ($cflag).\n";
        }
    }

    result(
        'problems, languages, teams',
        'Team affiliation icons',
        ($details == '') ? 'O' : 'W',
        $details
    );
} else {
    result(
        'problems, languages, teams',
        'Team affiliation icons',
        'O',
        'Affiliation icons disabled in config.'
    );
}


// check for teams with duplicate names
$res = $DB->q('SELECT name FROM team GROUP BY name HAVING COUNT(name) >= 2;');

$details = '';
while ($row = $res->next()) {
    $teamids = $DB->q('COLUMN SELECT teamid FROM team WHERE name=%s', $row['name']);
    $details .= "Multiple teams have the name '" . specialchars($row['name']) . "': " .
            implode(', ', $teamids) . "\n";
}

result(
    'problems, languages, teams',
    'Duplicate team names',
    ($details == '' ? 'O':'W'),
    $details
);

flushresults();

// SUBMISSIONS, JUDINGS

$submres = 'O';
$submnote = null;
if (! is_writable(SUBMITDIR)) {
    $submres = 'W';
    $submnote = 'The webserver has no write access to SUBMITDIR (' .
                specialchars(SUBMITDIR) . '), and thus will not ' .
                'be able to make backup copies of submissions.';
}

result('submissions and judgings', 'Submissions', $submres, $submnote);

// check for non-existent problem references
$res = $DB->q('SELECT s.submitid, s.probid, s.cid FROM submission s
               LEFT JOIN contestproblem p USING (cid,probid)
               WHERE p.shortname IS NULL ORDER BY submitid');

$details = '';
while ($row = $res->next()) {
    $details .= 'Submission s' .  $row['submitid'] . ' is for problem p' .
        $row['probid'] . ' while this problem is not found (in c'. $row['cid'] . ")\n";
}

$res = $DB->q('SELECT * FROM submission ORDER BY submitid');

while ($row = $res->next()) {
    $CHECKER_ERRORS = array();
    check_submission($row);
    if (count($CHECKER_ERRORS) > 0) {
        foreach ($CHECKER_ERRORS as $chk_err) {
            $details .= $row['submitid'].': ' . $chk_err ."\n";
        }
    }
}

// check for submissions that have no associated source file(s)
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN submission_file f USING (submitid)
               WHERE f.submitid IS NULL
               ORDER BY submitid');

while ($row = $res->next()) {
    $details .= 'Submission s' . $row['submitid'] .
                " does not have any associated source files\n";
}

// check for submissions that have been marked by a judgehost but that
// have no judging-row
$res = $DB->q('SELECT s.submitid FROM submission s
               LEFT OUTER JOIN judging j USING (submitid)
               WHERE j.submitid IS NULL AND s.judgehost IS NOT NULL
               ORDER BY submitid');

while ($row = $res->next()) {
    $details .= 'Submission s' . $row['submitid'] .
                " has a judgehost but no entry in judgings\n";
}

result(
    'submissions and judgings',
    'Submission integrity',
    ($details == '' ? 'O':'E'),
    $details
);


$details = '';
// check for more than one valid judging for a submission
$res = $DB->q('SELECT submitid, SUM(valid) as numvalid
               FROM judging GROUP BY submitid HAVING numvalid > 1
               ORDER BY submitid');
while ($row = $res->next()) {
    $details .= 'Submission s' . $row['submitid'] .
                ' has more than one valid judging (' . $row['numvalid'] . ")\n";
}

// check for valid judgings that are already running too long
$res = $DB->q('SELECT judgingid, submitid, starttime
               FROM judging WHERE valid = 1 AND endtime IS NULL AND
               (UNIX_TIMESTAMP()-starttime) > 300
               ORDER BY submitid, judgingid');
while ($row = $res->next()) {
    $details .= 'Judging s' . (int)$row['submitid'] . '/j' . (int)$row['judgingid'] .
                " is running for longer than 5 minutes, probably the judgedaemon crashed\n";
}

// check for start/endtime problems and contestids
$res = $DB->q('SELECT s.submitid AS s_submitid, j.submitid AS j_submitid,
               judgingid, starttime, endtime, submittime, s.cid AS s_cid, j.cid AS j_cid
               FROM judging j LEFT OUTER JOIN submission s USING (submitid)
               WHERE (j.cid != s.cid) OR (s.submitid IS NULL) OR
               (j.endtime IS NOT NULL AND j.endtime < j.starttime) OR
               (j.starttime < s.submittime)
               ORDER BY j_submitid');

while ($row = $res->next()) {
    $err = 'Judging j' . $row['judgingid'] . '/s' . $row['j_submitid'] . '';
    $CHECKER_ERRORS = array();
    if (!isset($row['s_submitid'])) {
        $CHECKER_ERRORS[] = 'has no corresponding submitid (in c'.$row['j_cid'] .')';
    }
    if ($row['s_cid'] != null && $row['s_cid'] != $row['j_cid']) {
        $CHECKER_ERRORS[] = 'Judging j' .$row['judgingid'] .
                            ' is from a different contest (c' . $row['j_cid'] .
                            ') than its submission s' . $row['j_submitid'] .
                            ' (c' . $row['s_cid'] . ')';
    }
    check_judging($row);
    if (count($CHECKER_ERRORS) > 0) {
        foreach ($CHECKER_ERRORS as $chk_err) {
            $details .= $err.': ' . $chk_err ."\n";
        }
    }
}

result(
    'submissions and judgings',
    'Judging integrity',
       ($details == '' ? 'O':'E'),
    $details
);

flushresults();

// REFERENTIAL INTEGRITY. Nothing should turn up here since
// we have defined foreign key relations between our tables.
if ($_SERVER['QUERY_STRING'] == 'refint') {
    $details = '';
    foreach ($RELATIONS as $table => $foreign_keys) {
        if (empty($foreign_keys)) {
            continue;
        }
        $fields = implode(', ', array_keys($foreign_keys));
        $res = $DB->q('SELECT ' . $fields . ' FROM ' . $table .
                      ' ORDER BY ' . implode(',', $KEYS[$table]));
        while ($row = $res->next()) {
            foreach ($foreign_keys as $foreign_key => $val) {
                list($target, $action) = explode('&', $val);
                if (empty($row[$foreign_key]) || $action=='NOCONSTRAINT') {
                    continue;
                }
                $f = explode('.', $target);
                if ($DB->q("VALUE SELECT count(*) FROM $f[0] WHERE $f[1] = %s",
                           $row[$foreign_key]) < 1) {
                    $details .= "foreign key constraint fails for $table.$foreign_key = \"" .
                                $row[$foreign_key] . "\" (not found in $target)\n";
                }
            }
        }
    }

    // problems found are of level warning, because the severity may be different depending
    // on which table it is.
    result(
        'referential integrity',
        'Inter-table relationships',
        ($details == '' ? 'O':'W'),
        $details
    );
} else {
    result(
        'referential integrity',
        'Inter-table relationships',
        'R',
        'Not checked.',
        '<a href="?refint">check now</a> (potentially slow operation)'
    );
}

flushresults();

echo "</table>\n\n";

// collapse all details; they are not collapsed in the default
// style sheet to keep things working with JavaScript disabled.
echo "<script type=\"text/javascript\">
<!--
for (var i = 0; i < $resultno; i++) {
    collapse(i);
}
// -->
</script>\n\n";

$time_end = microtime(true);

echo "<p>Config checker completed in ".round($time_end - $time_start, 2)." seconds.</p>\n\n";

echo "<p>Legend:
<img src=\"../images/s_okay.png\"      alt=\"O\" class=\"picto\" /> OK
<img src=\"../images/s_warn.png\"      alt=\"W\" class=\"picto\" /> Warning
<img src=\"../images/s_error.png\"     alt=\"E\" class=\"picto\" /> Error
</p>\n";

require(LIBWWWDIR . '/footer.php');
