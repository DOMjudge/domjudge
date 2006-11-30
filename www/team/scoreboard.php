<?php

/**
 * Scoreboard
 *
 * $Id$
 */

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/scoreboard.php';
$title = 'Scoreboard';
include('../header.php');

// call the general putScoreBoard function from scoreboad.php
putScoreBoard($login);

include('../footer.php');
