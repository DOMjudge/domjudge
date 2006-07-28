<?php
/**
 * View a problem
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id);
$title = 'Problem '.htmlspecialchars(@$id);

if ( ! $id ) error("Missing or invalid problem id");

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge' ) {
	rejudge('submission.probid',$id);
	header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
	exit;
}

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'toggle_submit' ) {
	$DB->q('UPDATE problem SET allow_submit = %i WHERE probid = %s',
		   $_POST['val'], $id);
}

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'toggle_judge' ) {
	$DB->q('UPDATE problem SET allow_judge = %i WHERE probid = %s',
		   $_POST['val'], $id);
}

require('../header.php');
require('menu.php');

echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM problem NATURAL JOIN contest WHERE probid = %s', $id);

?>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Contest:     </td><td><?=htmlspecialchars($data['cid']).' - '.
                                 htmlentities($data['contestname'])?></td></tr>
<tr><td>Allow submit:</td><td><?=printyn($data['allow_submit'])?>
<?php
$val = ! $data['allow_submit'];
$str = $val ? 'Allow' : 'Disallow';
?>
<form action="<?=$pagename?>" method="post">
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="toggle_submit" />
<input type="hidden" name="val" value="<?=$val?>" />
<input type="submit" value="toggle"
 onclick="return confirm('<?=$str?> submissions for this problem?')" />
</form></td></tr>
<tr><td>Allow judge: </td><td><?=printyn($data['allow_judge'])?>
<?php
$val = ! $data['allow_judge'];
$str = $val ? 'Allow' : 'Disallow';
?>
<form action="<?=$pagename?>" method="post">
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="toggle_judge" />
<input type="hidden" name="val" value="<?=$val?>" />
<input type="submit" value="toggle"
 onclick="return confirm('<?=$str?> judging for this problem?')" />
</form></td></tr>
<tr><td>Testdata:    </td><td class="filename"><?=htmlspecialchars($data['testdata'])?></td></tr>
<tr><td>Timelimit:   </td><td><?=(int)$data['timelimit']?></td></tr>
</table>

<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value="REJUDGE ALL for problem <?=$id?>"
 onclick="return confirm('Rejudge all submissions for this problem?')" />
</p>
</form>

<h2>Submissions for <?=htmlspecialchars($id)?></h2>

<?php
putSubmissions('probid', $id, TRUE);

require('../footer.php');
