<?php
/**
 * View team details
 *
 * $Id: team.php 1182 2006-12-01 23:01:31Z eldering $
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');

if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");

$title = 'Team '.htmlspecialchars(@$id);
$menu = false;
require('../header.php');

putTeam($id);

echo "<p><a href=\"".getBaseURI()."public\">return to scoreboard</a></p>\n\n";

require('../footer.php');
