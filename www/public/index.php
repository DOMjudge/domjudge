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
require('../header.php');

// call the general putScoreBoard function from common.php
putScoreBoard();

require('../footer.php');

