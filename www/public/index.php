<?php

/**
 * Produce a total score.
 *
 * $Id$
 */
 
require('init.php');
$title="Scoreboard";
$refresh="30;url=index.php";
require('../header.php');

putScoreBoard();

// last modified date
echo "<div id=\"lastmod\">Last Update: " . date('j M Y H:i') . "</div>\n\n";

require('../footer.php');

