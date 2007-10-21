<?php
/**
 * View the submissionqueue
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php';
$title = 'Submissions';

require('../header.php');

echo "<h1>Submissions</h1>\n\n";

putSubmissions($cdata, null,TRUE);

require('../footer.php');
