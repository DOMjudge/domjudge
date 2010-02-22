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
$printercss = TRUE;

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata);

require(LIBWWWDIR . '/footer.php');
