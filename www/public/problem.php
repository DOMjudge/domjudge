<?php
/**
 * View/download problem texts
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');

// download a given problem statement
$id = @$_REQUEST['id'];
if ( preg_match('/^' . IDENTIFIER_CHARS . '+$/', $id) ) {
	putProblemText($id);
	exit;
}

$title = 'Problem statements';
require(LIBWWWDIR . '/header.php');

echo "<h1>Problem statements</h1>\n\n";

putProblemTextList();

require(LIBWWWDIR . '/footer.php');
