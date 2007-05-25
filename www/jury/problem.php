<?php
/**
 * View a problem
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_REQUEST['id'];

require('init.php');

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id);
}

$title = 'Problem '.htmlspecialchars(@$id);

if ( !empty($pcmd) ) {
	if ( isset($pcmd['rejudge']) ) {
		rejudge('submission.probid',$id);
		header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
		exit;
	}

	if ( isset($pcmd['toggle_submit']) ) {
		$DB->q('UPDATE problem SET allow_submit = %i WHERE probid = %s',
			   $_POST['val']['toggle_submit'], $id);
	}

	if ( isset($pcmd['toggle_judge']) ) {
		$DB->q('UPDATE problem SET allow_judge = %i WHERE probid = %s',
			   $_POST['val']['toggle_judge'], $id);
	}
}

require('../header.php');

if ( IS_ADMIN && !empty($cmd) ):

	require('../forms.php');
	
	echo "<h2>" . ucfirst($cmd) . " problem</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n" .
		"<tr><td>Problem ID:</td><td>";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('TUPLE SELECT * FROM problem WHERE probid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][probid]', $row['probid']);
		echo htmlspecialchars($row['probid']);
	} else {
		echo addInput('data[0][probid]', null, 8, 10);
	}
	echo "</td></tr>\n";

?>
<tr><td>Contest:</td><td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT cid,contestname FROM contest ORDER BY cid");
echo addSelect('data[0][cid]', $cmap, @$row['cid'], true);
?>
</td></tr>
<tr><td>Problem name:</td><td><?=addInput('data[0][name]', @$row['name'], 20, 255)?></td></tr>
<tr><td>Allow submit:</td><td><?=addRadioBox('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> yes <?=addRadioBox('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> no</td></tr>
<tr><td>Allow judge:</td><td><?=addRadioBox('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> yes <?=addRadioBox('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> no</td></tr>
<tr><td>Path to testdata:</td><td><?=addInput('data[0][testdata]', @$row['testdata'], 20, 255)?></td></tr>
<tr><td>Timelimit:</td><td><?=addInput('data[0][timelimit]', @$row['timelimit'], 5, 5)?> sec</td></tr>
<tr><td>Balloon colour:</td><td><?=addInput('data[0][color]', @$row['color'], 8, 10)?></td></tr>
<tr><td>Special run script:</td><td><?=addInput('data[0][special_run]', @$row['special_run'], 10, 8)?></td></tr>
<tr><td>Special compare script:</td><td><?=addInput('data[0][special_compare]', @$row['special_compare'], 10, 8)?></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','problem') .
	addSubmit('Save') .
	addEndForm();

require('../footer.php');
exit;

endif;

if ( ! $id ) error("Missing or invalid problem id");

echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM problem NATURAL JOIN contest WHERE probid = %s', $id);

?>
<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="val[toggle_judge]" value="<?=!$data['allow_judge']?>" />
<input type="hidden" name="val[toggle_submit]" value="<?=!$data['allow_submit']?>" />
</p>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Contest:     </td><td><?=htmlspecialchars($data['cid']).' - '.
                                 htmlentities($data['contestname'])?></td></tr>
<tr><td>Allow submit:</td><td class="nobreak"><?=printyn($data['allow_submit'])?>
 <input type="submit" name="cmd[toggle_submit]" value="toggle"
 onclick="return confirm('<?= $data['allow_submit'] ? 'Disallow' : 'Allow' ?> submissions for this problem?')" />
</td></tr>
<tr><td>Allow judge: </td><td><?=printyn($data['allow_judge'])?>
 <input type="submit" name="cmd[toggle_judge]" value="toggle"
 onclick="return confirm('<?= $data['allow_judge'] ? 'Disallow' : 'Allow'?> judging for this problem?')" />
</td></tr>
<tr><td>Testdata:    </td><td class="filename"><?=htmlspecialchars($data['testdata'])?></td></tr>
<tr><td>Timelimit:   </td><td><?=(int)$data['timelimit']?></td></tr>
<?php
if ( isset($data['color']) ) {
	echo '<tr><td>Colour:       </td><td style="background: ' . htmlspecialchars($data['color']) .
		';">' . htmlspecialchars($data['color']) . "</td></tr>\n";
}
if ( isset($data['special_run']) ) {
	echo '<tr><td>Special run script:</td><td class="filename">' .
		htmlspecialchars($data['special_run']) . "</td></tr>\n";
}
if ( isset($data['special_compare']) ) {
	echo '<tr><td>Special compare script:</td><td class="filename">' .
		htmlspecialchars($data['special_compare']) . "</td></tr>\n";
}
?>
</table>

<p>
<input type="submit" name="cmd[rejudge]" value="REJUDGE ALL for problem <?=$id?>"
 onclick="return confirm('Rejudge all submissions for this problem?')" />
<?php
if ( IS_ADMIN ) {
	echo editLink('problem',$id) . " " .
		delLink('problem','probid', $id);
}
?>
</p>
</form>

<h2>Submissions for <?=htmlspecialchars($id)?></h2>

<?php

$restrictions = array( 'probid' => $id );
putSubmissions($restrictions, TRUE);

require('../footer.php');
