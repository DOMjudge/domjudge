<?php
/**
 * Download problem as zip archive.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
if (!isset($id)) {
    error("No problem id given.");
}

$ini_keys = array('shortname', 'timelimit', 'special_run',
                  'special_compare', 'color');

$problem = $DB->q('MAYBETUPLE SELECT * FROM problem p
                   LEFT JOIN contestproblem cp ON (p.probid=cp.probid AND cp.cid=%i)
                   WHERE p.probid = %i LIMIT 1', $cid, $id);

if (empty($problem)) {
    error("Problem p$id not found");
}

$inistring = "";
foreach ($ini_keys as $ini_val) {
    if (!empty($problem[$ini_val])) {
        $ini_val_final = $ini_val;
        if ($ini_val == 'shortname') {
            $ini_val_final = 'probid';
        }
        $inistring .= $ini_val_final . "='" . $problem[$ini_val] . "'\n";
    }
}

$probyaml = array();
$probyaml['name'] = $problem['name'];
if (!empty($problem['special_compare'])) {
    $probyaml['validation'] = 'custom';
}
if (!empty($problem['special_compare_args'])) {
    $probyaml['validator_flags'] = $problem['special_compare_args'];
}
if (!empty($problem['memlimit'])) {
    $probyaml['limits']['memory'] = (int)round($problem['memlimit']/1024);
}
if (!empty($problem['outputlimit'])) {
    $probyaml['limits']['output'] = (int)round($problem['outputlimit']/1024);
}

$yamlstring = '# Problem exported by DOMjudge on ' . date('c') . "\n" .
    Spyc::YAMLDump($probyaml, 4, 0);

$zip = new ZipArchive;
if (!($tmpfname = tempnam(TMPDIR, "export-"))) {
    error("Could not create temporary file.");
}

$res = $zip->open($tmpfname, ZipArchive::OVERWRITE);
if ($res !== true) {
    error("Could not create temporary zip file.");
}
$zip->addFromString('domjudge-problem.ini', $inistring);
$zip->addFromString('problem.yaml', $yamlstring);

if (!empty($problem['problemtext'])) {
    $zip->addFromString('problem_statement/problem.'.$problem['problemtext_type'], $problem['problemtext']);
    unset($problem['problemtext']);
}

$testcases = $DB->q('SELECT description, testcaseid, rank, sample, image_type
                     FROM testcase WHERE probid = %i ORDER BY rank', $id);
while ($tc = $testcases->next()) {
    $fname = 'data/' . ($tc['sample'] ? 'sample/' : 'secret/') . $tc['rank'];
    foreach (array('in','out') as $inout) {
        $content = $DB->q("VALUE SELECT SQL_NO_CACHE " . $inout . "put FROM testcase
                           WHERE testcaseid = %i", $tc['testcaseid']);
        $zip->addFromString($fname.'.'.str_replace('out', 'ans', $inout), $content);
        unset($content);
    }
    if (!empty($tc['description'])) {
        $content = $tc['description'];
        if (strstr($content, "\n")===false) {
            $content .= "\n";
        }
        $zip->addFromString($fname.'.desc', $content);
    }
    if (!empty($tc['image_type'])) {
        $content = $DB->q("VALUE SELECT SQL_NO_CACHE image FROM testcase
                           WHERE testcaseid = %i", $tc['testcaseid']);
        $zip->addFromString($fname.'.'.$tc['image_type'], $content);
        unset($content);
    }
}

$solutions = $DB->q('SELECT submitid, expected_results
                     FROM submission
                     LEFT JOIN submission_file USING(submitid)
                     WHERE cid = %i AND probid = %i AND expected_results IS NOT NULL
                     GROUP BY submitid', $cid, $id);

while ($sol = $solutions->next()) {
    $result = dj_json_decode($sol['expected_results']);
    // Only support single outcome solutions:
    if (!is_array($result) || count($result)!=1) {
        continue;
    }
    $result = reset($result);

    $probresult = null;
    foreach ($problem_result_remap as $key => $val) {
        if (trim(mb_strtoupper($result))==$val) {
            $probresult = mb_strtolower($key);
        }
    }
    if (!isset($probresult)) {
        continue;
    } // unsupported result

    // NOTE: we store *all* submissions inside a subdirectory, also
    // single-file submissions. This is to prevent filename clashes
    // since we can't change the filename to something unique, since
    // that could break e.g. Java sources, even if _we_ support this
    // by default.
    $dirname = 'submissions/' . $probresult . '/s'.$sol['submitid'].'/';

    $sources = $DB->q('SELECT sourcecode, filename
                       FROM submission_file
                       WHERE submitid = %i ORDER BY rank ASC', $sol['submitid']);

    while ($source = $sources->next()) {
        $zip->addFromString($dirname.$source['filename'], $source['sourcecode']);
    }
}

$zip->close();

$filename = 'p' . $id . '-' . $problem['shortname'] . '.zip';

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Type: application/zip; name=\"$filename\"");
header("Content-Length: " . filesize($tmpfname) . "\n\n");
header("Content-Transfer-Encoding: binary");

readfile($tmpfname);
unlink($tmpfname);
