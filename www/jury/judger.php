<?php
/**
 * View judger details
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url='.$_SERVER["REQUEST_URI"];
$title = 'Judger';
require('../header.php');
require('menu.php');

$id = $_REQUEST['id'];
if(!$id || !preg_match("/^[A-Za-z0-9_\-.]*$/", $id))	error ("Invalid judger id");

if(isset($_POST['cmd'])) {
	if($_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate') {
		$DB->q('UPDATE judger SET active = %i WHERE judgerid = %s'
		      ,($_POST['cmd'] == 'activate'?1:0), $id);
	}
}
$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $id);

?>

<h1>Judger <?=printhost($row['judgerid'])?></h1>

<table>
<tr><td>Name:</td><td><?=printhost($row['judgerid'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
</table>

<?	$cmd = ($row['active'] == 1?'deactivate':'activate'); ?>
<p>
<form action="judger.php" method="post">
<input type="hidden" name="id" value="<?=htmlspecialchars($row['judgerid'])?>" />
<input type="hidden" name="cmd" value="<?=$cmd?>" />
<input type="submit" value=" <?=$cmd?> " />
</form>

<h3>Judgings by <?=printhost($row['judgerid'])?></h3>
<?

getJudgings('judgerid', $row['judgerid']);

require('../footer.php');
