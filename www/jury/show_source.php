<?php
/**
 * Show source code from the database.
 *
 * $Id$
 */

require('init.php');
$title = 'Show Source';
require('../header.php');
require('menu.php');

$id = (int)$_GET['id'];

$source = $DB->q('TUPLE SELECT sourcefile,sourcecode FROM submission WHERE submitid = %i',$id);

echo "<h2 class=\"filename\">".htmlspecialchars($source['sourcefile'])."</h2>\n\n";

echo '<pre class="output_text">' .
	htmlspecialchars($source['sourcecode']) .
	"</pre>\n\n";

require('../footer.php');
