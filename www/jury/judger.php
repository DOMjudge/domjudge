<?php
/**
 * View judger details
 *
 * $Id$
 */

require('init.php');
$title = 'Judger';
require('../header.php');
require('menu.php');

$id = (int)$_REQUEST['id'];
if(!$id)	error ("Missing judger id");

if(isset($_POST['cmd'])) {
	if($_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate') {
		$DB->q('UPDATE judger SET active = %i WHERE judgerid = %i'
		      ,($_POST['cmd'] == 'activate'?1:0), $id);
	}
}
$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $id);

?>

<h1>Judger <?=printhost($row['name'])?></h1>

<table>
<tr><td>ID:</td><td><?=$id?></td></tr>
<tr><td>Name:</td><td><?=printhost($row['name'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
</table>

<h3>Judgings by <?=printhost($row['name'])?></h3>

<?php

$res = $DB->q('SELECT * FROM judging WHERE judgerid = %i AND cid = %i ORDER BY starttime DESC',
	$id, getCurCont() );

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
$cmd = ($row['active'] == 1?'deactivate':'activate');
?>
<p>
<form action="judger.php" method="post">
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="<?=$cmd?>" />
<input type="submit" value=" <?=$cmd?> " />
</form>
<?
require('../footer.php');
