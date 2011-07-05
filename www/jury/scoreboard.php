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

// set filter options
$first = TRUE;
$filter = array();
foreach( array('affilid', 'country', 'categoryid') as $type ) {
	$val = @$_GET[$type];
	if ( isset($val) && !empty($val) ) {
		$filter[$type] = array($val);
		$refresh .= ($first ? '?' : '&') . $opt . "=" . urlencode($val);
		$first = FALSE;
	}
}

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, NULL, FALSE, $filter);

require(LIBWWWDIR . '/footer.php');
