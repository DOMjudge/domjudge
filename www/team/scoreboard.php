<?php

/**
 * Scoreboard
 *
 * $Id$
 */

require('init.php');
$title = 'Scoreboard';
include('../header.php');
include('menu.php');

// call the general putScoreBoard function from common.php
putScoreBoard($login);

include('../footer.php');
