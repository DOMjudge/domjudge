<?php
/**
 * View the submissionqueue
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php';
$title = 'Submissions';
require('../header.php');
require('menu.php');

echo "<h1>Submissions</h1>\n\n";

putSubmissions('','',TRUE);

require('../footer.php');
