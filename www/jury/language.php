<?php
/**
 * View a language
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');
$title = 'Language '.htmlspecialchars(@$id);

if ( ! $id ) error("Missing or invalid language id");

if ( !empty($_POST['cmd']) ) {
	if ( isset($_POST['cmd']['rejudge']) ) {
		rejudge('submission.langid',$id);
		header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
		exit;
	}

	if ( isset($_POST['cmd']['toggle_submit']) ) {
		$DB->q('UPDATE language SET allow_submit = %i WHERE langid = %s',
		       $_POST['val']['toggle_submit'], $id);
	}

	if ( isset($_POST['cmd']['toggle_judge']) ) {
		$DB->q('UPDATE language SET allow_judge = %i WHERE langid = %s',
		       $_POST['val']['toggle_judge'], $id);
	}
}


require('../header.php');

echo "<h1>Language ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s', $id);

?>
<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="val[toggle_judge]" value="<?=!$data['allow_judge']?>" />
<input type="hidden" name="val[toggle_submit]" value="<?=!$data['allow_submit']?>" />
</p>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['langid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Extension:   </td><td class="filename">.<?=htmlspecialchars($data['extension'])?></td></tr>
<tr><td>Allow submit:</td><td><?=printyn($data['allow_submit'])?>
 <input type="submit" name="cmd[toggle_submit]" value="toggle"
 onclick="return confirm('<?= $data['allow_submit'] ? 'Disallow' : 'Allow' ?> submissions for this language?')" />
</td></tr>
<tr><td>Allow judge: </td><td><?=printyn($data['allow_judge'])?>
 <input type="submit" name="cmd[toggle_judge]" value="toggle"
 onclick="return confirm('<?= $data['allow_judge'] ? 'Disallow' : 'Allow'?> judging for this language?')" />
</td></tr>
<tr><td>Time factor:  </td><td><?=htmlspecialchars($data['time_factor'])?></td></tr>
</table>

<p>
<input type="submit" name="cmd[rejudge]" value="REJUDGE ALL for language <?=htmlentities($data['name'])?>"
 onclick="return confirm('Rejudge all submissions for this language?')" />
</p>
</form>

<h2>Submissions in <?=htmlspecialchars($id)?></h2>

<?php

$restrictions = array( 'langid' => $id );
putSubmissions($restrictions, TRUE);

require('../footer.php');
