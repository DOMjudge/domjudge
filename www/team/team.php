<?php
/**
 * View team details
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$id = $_REQUEST['id'];

require('init.php');

if ( ! is_numeric($id) ) error("Missing or invalid team id");

$title = 'Team t'.htmlspecialchars(@$id);
require(LIBWWWDIR . '/header.php');

putTeam($id);

require(LIBWWWDIR . '/footer.php');
