<?php
/**
 * View/download problem texts
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Problem statements';
require(LIBWWWDIR . '/header.php');

echo "<h1>Problem statements</h1>\n\n";

putProblemTextList();

require(LIBWWWDIR . '/footer.php');
