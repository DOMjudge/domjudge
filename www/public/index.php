<?php

/**
 * Produce a total score.
 *
 * $Id$
 */
 
require('init.php');
$title="Scoreboard";
// set auto refresh
$refresh="30;url=index.php";
require('../header.php');

// call the general putScoreBoard function from common.php
putScoreBoard();

// last modified date, now.
echo "<div id=\"lastmod\">Last Update: " . date('j M Y H:i') . "</div>\n\n";

require('../footer.php');

