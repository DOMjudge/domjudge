<?php

/**
 * Scoreboard
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$refresh = array(
    'after' => 30,
    'url' => 'scoreboard.php',
);
$title = 'Scoreboard';
$printercss = true;

require(LIBWWWDIR . '/scoreboard.php');

// This reads and sets a cookie, so must be called before headers are sent.
$filter = initScorefilter();

require(LIBWWWDIR . '/header.php');

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, null, false, $filter);

require(LIBWWWDIR . '/footer.php');
