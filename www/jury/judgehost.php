<?php
/**
 * View judgehost details
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id);
$title = 'Judgehost '.htmlspecialchars(@$id);

if ( ! $id || ! preg_match("/^[A-Za-z0-9_\-.]*$/", $id)) {
	error("Missing or invalid judge hostname");
}

if ( IS_ADMIN && isset($_POST['cmd']) ) {
	if ( $_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate' ) {
		$DB->q('UPDATE judgehost SET active = %i WHERE hostname = %s',
		       ($_POST['cmd'] == 'activate' ? 1 : 0), $id);
	}
	if ( $_POST['cmd'] == 'rejudge' ) {
		rejudge('judging.judgehost',$id);
		header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
		exit;
	}
}

$row = $DB->q('TUPLE SELECT * FROM judgehost WHERE hostname = %s', $id);

require('../header.php');

echo "<h1>Judgehost ".printhost($row['hostname'])."</h1>\n\n";

?>

<table>
<tr><td>Name:  </td><td><?=printhost($row['hostname'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
</table>

<?php
if ( IS_ADMIN ) :

$cmd = ($row['active'] == 1 ? 'deactivate' : 'activate'); ?>
<form action="judgehost.php" method="post">
<p>
<input type="hidden" name="id" value="<?=htmlspecialchars($row['hostname'])?>" />
<input type="hidden" name="cmd" value="<?=$cmd?>" />
<input type="submit" value=" <?=$cmd?> " />
</p>
</form>
<?php endif; ?>

<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" REJUDGE ALL for judgehost <?=$id?> "
 onclick="return confirm('Rejudge all submissions for this judgehost?')" />
</p>
</form>

<h3>Judgings by <?=printhost($row['hostname'])?></h3>
<?php

putJudgings('judgehost', $row['hostname']);

require('../footer.php');
