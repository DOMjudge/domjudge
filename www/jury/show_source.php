<?php
/**
 * Show a source file
 *
 * $Id$
 */

require('init.php');
$title = 'Show Source';
require('../header.php');
require('menu.php');

$id = (int)$_GET['id'];

$filename = $DB->q('VALUE SELECT source FROM submission WHERE submitid = %i',$id);

echo "<h2 class=\"filename\">".htmlspecialchars($filename)."</h2>\n\n";

$file = @file(SUBMITDIR.'/'.$filename);
if(!$file) {
	error ( "Couldn't open file ".SUBMITDIR.'/'.$filename );
}
echo '<pre class="output_text">';
foreach($file as $line) {
	echo htmlspecialchars($line);
}
echo "</pre>\n\n";

require('../footer.php');
