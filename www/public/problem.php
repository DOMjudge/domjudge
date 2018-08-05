<?php
/**
 * View/download a specific problem text or sample testcase.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
if (empty($id)) {
    error("Missing problem id");
}

if (!isset($_GET['testcase'])) {
    // download a given problem statement
    putProblemText($id);
} else {
    if (is_numeric($_GET['testcase']) && isset($_GET['type']) &&
        ($_GET['type'] === 'in' || $_GET['type'] === 'out')) {
        $testcasetype = $_GET['type'];
        $testcaseseq = $_GET['testcase'];
        putSampleTestcase($id, $testcaseseq, $testcasetype);
    } else {
        error("Invalid arguments for sample testcase.");
    }
}
