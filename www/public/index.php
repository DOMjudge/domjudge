<?php
/**
 * Produce a total score. Call with parameter 'static' for
 * output suitable for static HTML pages.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
 
require('init.php');
$title="Scoreboard";
// set auto refresh
$refresh="30;url=./";
$menu = false;
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

$isstatic = @$_SERVER['argv'][1] == 'static' || isset($_REQUEST['static']);

if ( ! $isstatic ) putClock();

// call the general putScoreBoard function from scoreboard.php
putScoreBoard(getCurContest(TRUE), null, $isstatic);

require(LIBWWWDIR . '/footer.php');
