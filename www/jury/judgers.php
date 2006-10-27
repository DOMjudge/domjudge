<?php
/**
 * View the judgers
 *
 * $Id$
 */

require('init.php');
$title = 'Judgers';

if ( !empty($_POST['cmd']) ) {
	if ( $_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate' ) {
		$DB->q('UPDATE judger SET active = %i',
		       ($_POST['cmd'] == 'activate' ? 1:0));
	}
}

$res = $DB->q('SELECT * FROM judger ORDER BY judgerid');

require('../header.php');
require('menu.php');

echo "<h1>Judgers</h1>\n\n";

if( $res->count() == 0 ) {
	echo "<p><em>No judgers defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<tr><th>judgerid</th><th>active</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr".( $row['active'] ? '': ' class="disabled"').
			"><td><a href=\"judger.php?id=".urlencode($row['judgerid']).'">'.
			printhost($row['judgerid']).'</a>'.
			"</td><td align=\"center\">".printyn($row['active'])."</td></tr>\n";
	}
	echo "</table>\n\n";
?>

<form action="judgers.php" method="post"><p>
<input type="hidden" name="cmd" value="activate" />
<input type="submit" value="Start all judgers!" />
</p></form>

<form action="judgers.php" method="post"><p>
<input type="hidden" name="cmd" value="deactivate" />
<input type="submit" value="Stop all judgers!" />
</p></form>

<?php
}
require('../footer.php');
