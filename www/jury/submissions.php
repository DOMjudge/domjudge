<?php
/**
 * View the submissionqueue
 *
 * $Id$
 */

require('init.php');
$title = 'Submissions';
require('../header.php');

echo "<h1>Submissions</h1>\n\n";

getSubmissions();

require('../footer.php');
