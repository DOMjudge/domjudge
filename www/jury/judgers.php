<?php
/**
 * View the judgers
 *
 * $Id$
 */

require('init.php');
$title = 'Judgers';
require('../header.php');
require('menu.php');

echo "<h1>Judgers</h1>\n\n";

if(isset($_POST['cmd'])) {
	if($_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate') {
		$DB->q('UPDATE judger SET active = %i WHERE 1'
		      ,($_POST['cmd'] == 'activate'?1:0));
	}
}

$res = $DB->q('SELECT * FROM judger ORDER BY name');

echo "<table>
<tr><th>nr</th><th>name</th><th>active</th></tr>\n";
while($row = $res->next()) {
	echo "<tr".
		( $row['active'] ? '': ' class="disabled"').
		"><td><a href=\"judger.php?id=".(int)$row['judgerid'].'">'.(int)$row['judgerid'].'</a>'.
		"</td><td>".printhost($row['name']).
		"</td><td align=\"center\">".printyn($row['active']).
		"</td></tr>\n";
}
echo "</table>\n\n";
?>
<p>
<form action="judgers.php" method="post">
<input type="hidden" name="cmd" value="activate" />
<input type="submit" value=" Go Judgers! " />
</form>
<form action="judgers.php" method="post">
<input type="hidden" name="cmd" value="deactivate" />
<input type="submit" value=" Stop Judgers! " />
</form>
<?
require('../footer.php');
