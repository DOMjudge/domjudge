<?php

/**
 * Scoreboard
 *
 * $Id$
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'jury/scoreboard.php';
$title = 'Scoreboard';

include('../header.php');
include('menu.php');
require('../scoreboard.php');

// call the general putScoreBoard function from scoreboard.php
// and pass that we're the jury so we can view the current scores anytime.
putScoreBoard(null, TRUE);

include('../footer.php');
