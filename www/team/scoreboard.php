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
$refresh = '30;url=scoreboard.php';
$title = 'Scoreboard';
require(LIBWWWDIR . '/header.php');

// call the general putScoreBoard function from scoreboad.php
putScoreBoard($cdata, $login);

require(LIBWWWDIR . '/footer.php');
