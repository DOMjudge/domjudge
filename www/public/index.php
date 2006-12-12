<?php

/**
 * Produce a total score.
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

// call the general putScoreBoard function from scoreboard.php
putScoreBoard();

require('../footer.php');

