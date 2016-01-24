<?php
/**
 * View/download problem texts and sample testcases
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Contest problems';
require(LIBWWWDIR . '/header.php');

echo "<h1>Contest problems</h1>\n\n";

putProblemTextList();

require(LIBWWWDIR . '/footer.php');
