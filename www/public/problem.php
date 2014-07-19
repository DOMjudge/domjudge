<?php
/**
 * View/download a specific problem text. This page could later be
 * extended to provide more details, like sample test cases.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
if ( empty($id) ) error("Missing problem id");

// download a given problem statement
putProblemText($id);
exit;
