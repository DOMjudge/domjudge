<?php
/**
 * View the judgehosts
 *
 * $Id$
 */

require('init.php');
$title = 'Judgehosts';

require('../header.php');

echo "<h1>Judgehosts</h1>\n\n";

if ( IS_ADMIN ) {
	@$cmd = $_REQUEST['cmd'];
	if ( !empty($cmd) ) {
		if ( $cmd == 'activate' || $cmd == 'deactivate' ) {
			$DB->q('UPDATE judgehost SET active = %i',
			       ($cmd == 'activate' ? 1:0));
		}
		if ( $cmd == 'save' ) {
			foreach($_POST['judgehost'] as $id => $judgehost) {
				if ( !empty($judgehost) ) {
					$DB->q("REPLACE INTO judgehost (hostname,active) VALUES (%s,%i)",
						$judgehost, (@$_POST['active'][$id]?1:0));
				}
			}
		}
		if ( $cmd == 'add' || $cmd == 'edit' ) {
			echo "<form action=\"judgehosts.php\" method=\"post\">\n<table>\n" .
				"<tr><th>Hostname</th><th>Active</th></tr>\n";
			if ( $cmd == 'add' ) {
				for ($i=0; $i<10; ++$i) {
					echo "<tr><td><input type=\"text\" name=\"judgehost[$i]\"".
						"value=\"\" size=\"20\" maxlength=\"50\" /></td><td>".
						"<input type=\"checkbox\" name=\"active[$i]\" value=\"1\" checked=\"checked\" />".
						"</td></tr>\n";
				}
			} else {
				$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');
				$i = 0;
				while ( $row = $res->next() ) {
					echo "<tr><td><input type=\"hidden\" name=\"judgehost[$i]\"".
						"value=\"" . htmlspecialchars($row['hostname']) . "\" ".
						"/>" . htmlspecialchars($row['hostname']) . "</td><td>".
						"<input type=\"checkbox\" name=\"active[$i]\" value=\"1\" ".
						($row['active'] ?"checked=\"checked\" ":"") . "/>".
						"</td></tr>\n";
					++$i;
				}

			}
			echo "</table>\n\n<br /><br />\n<input type=\"hidden\" name=\"cmd\" value=\"save\" />\n" .
				"<input type=\"submit\" value=\"Save Judgehosts\" />\n</form>\n\n";

			require('../footer.php');
			exit;
			
		}
	}

}

$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');


if( $res->count() == 0 ) {
	echo "<p><em>No judgehosts defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<tr><th>hostname</th><th>active</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr".( $row['active'] ? '': ' class="disabled"').
			"><td><a href=\"judgehost.php?id=".urlencode($row['hostname']).'">'.
			printhost($row['hostname']).'</a>'.
			"</td><td align=\"center\">".printyn($row['active'])."</td>";
		if ( IS_ADMIN ) {
			echo "<td>" . delLink('judgehost','hostname',$row['hostname']) ."</td>";
		}
		echo "</tr>\n";
	}
	echo "</table>\n\n";

if ( IS_ADMIN ) :
?>

<form action="judgehosts.php" method="post"><p>
<input type="hidden" name="cmd" value="activate" />
<input type="submit" value="Start all judgehosts!" />
</p></form>

<form action="judgehosts.php" method="post"><p>
<input type="hidden" name="cmd" value="deactivate" />
<input type="submit" value="Stop all judgehosts!" />
</p></form>

<p><a href="judgehosts.php?cmd=add">add new judgehosts</a><br />
<a href="judgehosts.php?cmd=edit">edit judgehosts</a></p>

<?php
endif;

}
require('../footer.php');
