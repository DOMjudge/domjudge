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

$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $id);

?>

<h1>Judger <?=htmlspecialchars($row['name'])?></h1>

<table>
<tr><td>ID:</td><td><?=$id?></td></tr>
<tr><td>Name:</td><td><?=htmlspecialchars($row['name'])?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
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
			"><td><a href=\"judging.php?id=".(int)$jud['judgingid'].'">'.
			(int)$jud['judgingid']."</a></td><td>".printtime($jud['starttime']).
			"</td><td>".printtime(@$jud['endtime']).
			"</td><td>".printresult(@$jud['result'], $jud['valid']).
			"</td><td align=\"center\">".printyn($jud['valid']).
			"</td></tr>\n";
	}
	echo "</table>";
}

require('../footer.php');
