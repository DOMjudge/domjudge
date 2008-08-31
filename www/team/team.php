<?php
/**
 * View team details
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');

if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");

$title = 'Team '.htmlspecialchars(@$id);
require(SYSTEM_ROOT . '/lib/www/header.php');

putTeam($id);

require(SYSTEM_ROOT . '/lib/www/footer.php');
