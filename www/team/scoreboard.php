<?php

/**
 * Gives a team the details of a judging of their submission: errors etc.
 *
 * $Id: submission_details.php 356 2004-06-27 14:36:33Z nkp0405 $
 */

require('init.php');
$title = 'Scoreboard';
include('../header.php');
include('menu.php');

putScoreBoard($login);

include('../footer.php');
