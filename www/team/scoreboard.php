<?php
/**
 * Scoreboard
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/scoreboard.php';
$title = 'Scoreboard';
include('../header.php');

// call the general putScoreBoard function from scoreboad.php
putScoreBoard($cdata, $login);

include('../footer.php');
