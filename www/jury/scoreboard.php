<?php

/**
 * Scoreboard
 *
 * $Id$
 */

require('init.php');
$refresh = '30;url='.$_SERVER["REQUEST_URI"];
$title = 'Scoreboard';
include('../header.php');
include('menu.php');

// call the general putScoreBoard function from common.php
// and pass that we're the jury so we can view the current scores anytime.
putScoreBoard(null, TRUE);

include('../footer.php');
