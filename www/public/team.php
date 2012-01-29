<?php
/**
 * View team details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_GET['id'];

require('init.php');

if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");

$title = 'Team '.htmlspecialchars(@$id);
$menu = false;
require(LIBWWWDIR . '/header.php');

putTeam($id);

echo "<p><a href=\"./\">return to scoreboard</a></p>\n\n";

require(LIBWWWDIR . '/footer.php');
