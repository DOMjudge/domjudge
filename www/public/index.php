<?php

/**
 * Produce a total score. Call with parameter 'static' for
 * output suitable for static HTML pages.
 *
 * $Id$
 */
 
require('init.php');
$title="Scoreboard";
// set auto refresh
$refresh="30;url=" . getBaseURI() . 'public/';
$menu = false;
require('../header.php');
require('../scoreboard.php');

$isstatic = @$_SERVER['argv'][1]=='static';

if ( ! $isstatic ) putClock();

// call the general putScoreBoard function from scoreboard.php
putScoreBoard(null,null,$isstatic);

require('../footer.php');
