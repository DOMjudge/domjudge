<?php
/**
 * View the submissionqueue
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url='.$_SERVER["REQUEST_URI"];
$title = 'Submissions';
require('../header.php');
require('menu.php');

echo "<h1>Submissions</h1>\n\n";

getSubmissions();

require('../footer.php');
