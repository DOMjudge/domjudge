<?php
/**
 * View the judgehosts
 *
 * $Id$
 */

require('init.php');
$title = 'Judgehosts';

if ( !empty($_REQUEST['cmd']) ) {
	if ( $_REQUEST['cmd'] == 'activate' || $_REQUEST['cmd'] == 'deactivate' ) {
		$DB->q('UPDATE judgehost SET active = %i',
		       ($_REQUEST['cmd'] == 'activate' ? 1:0));
	}
}

$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');

require('../header.php');

echo "<h1>Judgehosts</h1>\n\n";

if( $res->count() == 0 ) {
	echo "<p><em>No judgehosts defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<tr><th>hostname</th><th>active</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr".( $row['active'] ? '': ' class="disabled"').
			"><td><a href=\"judgehost.php?id=".urlencode($row['hostname']).'">'.
			printhost($row['hostname']).'</a>'.
			"</td><td align=\"center\">".printyn($row['active'])."</td></tr>\n";
	}
	echo "</table>\n\n";
?>

<form action="judgehosts.php" method="post"><p>
<input type="hidden" name="cmd" value="activate" />
<input type="submit" value="Start all judgehosts!" />
</p></form>

<form action="judgehosts.php" method="post"><p>
<input type="hidden" name="cmd" value="deactivate" />
<input type="submit" value="Stop all judgehosts!" />
</p></form>

<?php
}
require('../footer.php');
