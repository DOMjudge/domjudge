<?php

/**
 * Produce a total score.
 *
 * $Id$
 */
 
require('init.php');
$title="Scoreboard";
require('../header.php');

putScoreBoard();

// last modified date
echo "<div id=\"lastmod\">Last Update: " . date('j M Y H:i') . "</div>\n\n";

require('../footer.php');

