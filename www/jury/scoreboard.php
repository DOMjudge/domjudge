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
$refresh = '30;url=' . getBaseURI() . 'jury/scoreboard.php';
$title = 'Scoreboard';
$printercss = TRUE;

include(SYSTEM_ROOT . '/lib/www/header.php');
require(SYSTEM_ROOT . '/lib/www/scoreboard.php');

// call the general putScoreBoard function from scoreboard.php
// and pass that we're the jury so we can view the current scores anytime.
putScoreBoard($cdata, null, TRUE);

include(SYSTEM_ROOT . '/lib/www/footer.php');
