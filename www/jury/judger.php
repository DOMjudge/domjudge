<?php
/**
 * View judger details
 *
 * $Id$
 */

$id = $_REQUEST['id'];

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/judger.php?id='.urlencode($id);
$title = 'Judger';
require('../header.php');
require('menu.php');

if ( ! $id || ! preg_match("/^[A-Za-z0-9_\-.]*$/", $id)) error ("Invalid judger id");

if ( isset($_POST['cmd']) ) {
	if ( $_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate' ) {
		$DB->q('UPDATE judger SET active = %i WHERE judgerid = %s',
		       ($_POST['cmd'] == 'activate' ? 1 : 0), $id);
	}
}
$row = $DB->q('TUPLE SELECT * FROM judger WHERE judgerid = %s', $id);

?>

<h1>Judger <?=printhost($row['judgerid'])?></h1>

<table>
<tr><td>Name:</td><td><?=printhost($row['judgerid'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
</table>

<?php	$cmd = ($row['active'] == 1 ? 'deactivate' : 'activate'); ?>
<form action="judger.php" method="post">
<p><input type="hidden" name="id" value="<?=htmlspecialchars($row['judgerid'])?>" />
<input type="hidden" name="cmd" value="<?=$cmd?>" />
<input type="submit" value=" <?=$cmd?> " /></p>
</form>

<h3>Judgings by <?=printhost($row['judgerid'])?></h3>
<?php

putJudgings('judgerid', $row['judgerid']);

require('../footer.php');
