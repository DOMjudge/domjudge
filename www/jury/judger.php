<?php
/**
 * View judger details
 *
 * $Id$
 */

require('init.php');
$title = 'Judger';
require('../header.php');

$id = (int)$_GET['id'];

echo "<h1>Judger $id</h1>\n\n";

$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $id);
?>

<table>
<tr><td>ID:</td><td><?=$row['judgerid']?></td></tr>
<tr><td>Name:</td><td><?=htmlspecialchars($row['name'])?></td></tr>
<tr><td>Active:</td><td><?=$row['active']?></td></tr>
</table>

<h3>Judgings by <?=htmlspecialchars($row['name'])?></h3>

<?php

$res = $DB->q('SELECT * FROM judging WHERE judgerid = %i', $id);

if( $res->count() == 0 ) {
	echo "<em>Nothing judged.</em>";
} else {
	echo "<table>\n\n".
		"<tr><th>ID</th><th>start</th><th>end</th><th>result</th><th>valid</th>\n";
	while( $jud = $res->next() ) {
		echo "<tr".($jud['valid'] ? '':' class="disabled"').
			"><td><a href=\"judging.php?id=".$jud['judgingid'].'">'.
			$jud['judgingid']."</a></td><td>".printtime($jud['starttime']).
			"</td><td>".printtime(@$jud['endtime']).
			"</td><td>".printresult(@$jud['result'], $jud['valid']).
			"</td><td align=\"center\">".$jud['valid'].
			"</td></tr>\n";
	}
	echo "</table>";
}

require('../footer.php');
