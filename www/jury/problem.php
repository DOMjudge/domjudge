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

if ( !empty($_POST['cmd']) ) {
	if ( isset($_POST['cmd']['rejudge']) ) {
		rejudge('submission.probid',$id);
		header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
		exit;
	}

	if ( isset($_POST['cmd']['toggle_submit']) ) {
		$DB->q('UPDATE problem SET allow_submit = %i WHERE probid = %s',
			   $_POST['val']['toggle_submit'], $id);
	}

	if ( isset($_POST['cmd']['toggle_judge']) ) {
		$DB->q('UPDATE problem SET allow_judge = %i WHERE probid = %s',
			   $_POST['val']['toggle_judge'], $id);
	}
}

require('../header.php');
require('menu.php');

echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM problem NATURAL JOIN contest WHERE probid = %s', $id);

?>

<form action="<?=$pagename?>" method="post">
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="val[toggle_judge]" value="<?=!$data['allow_judge']?>" />
<input type="hidden" name="val[toggle_submit]" value="<?=!$data['allow_submit']?>" />

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
</table>

<p>
<input type="submit" name="cmd[rejudge]" value="REJUDGE ALL for problem <?=$id?>"
 onclick="return confirm('Rejudge all submissions for this problem?')" />
</p>
</form>

<h2>Submissions for <?=htmlspecialchars($id)?></h2>

<?php
putSubmissions('probid', $id, TRUE);

require('../footer.php');
