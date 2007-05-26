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

@$cmd = @$_REQUEST['cmd'];
if ( IS_ADMIN && ($cmd == 'activate' || $cmd == 'deactivate') ) {
	$DB->q('UPDATE judgehost SET active = %i',
	       ($cmd == 'activate' ? 1:0));
}
if ( IS_ADMIN && ($cmd == 'add' || $cmd == 'edit') ) {
	require ( '../forms.php' ) ;
	echo addForm('edit.php');
	echo "\n<table>\n" .
		"<tr><th>Hostname</th><th>Active</th></tr>\n";
	if ( $cmd == 'add' ) {
		for ($i=0; $i<10; ++$i) {
			echo "<tr><td>" .
				addInput("data[$i][hostname]", null, 20, 50) .
				"</td><td>" .
				addSelect("data[$i][active]", 
					array(1=>'yes',0=>'no'), '1', true) .
				"</td></tr>\n";
		}
	} else {
		$res = $DB->q('SELECT * FROM judgehost ORDER BY hostname');
		$i = 0;
		while ( $row = $res->next() ) {
			echo "<tr><td>" .
				addHidden("keydata[$i][hostname]", $row['hostname']) .
				printhost($row['hostname']) .
				"</td><td>" .
				addSelect("data[$i][active]", 
					array(1=>'yes',0=>'no'), $row['active'], true) .
				"</td></tr>\n";
			++$i;
		}
	}
	echo "</table>\n\n<br /><br />\n";
	echo addHidden('cmd', $cmd) .
		( $cmd == 'add' ? addHidden('skipwhenempty', 'hostname') : '' ) .
		addHidden('table','judgehost') .
		addSubmit('Save Judgehosts') .
		addEndForm();

	require('../footer.php');
	exit;
	
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
