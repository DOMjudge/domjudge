<?php
/**
 * View a language
 *
 * $Id$
 */

$id = $_REQUEST['id'];

require('init.php');
$title = 'Language '.htmlspecialchars(@$id);
require('../header.php');
require('menu.php');

if ( ! $id ) error ("Missing language id");

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge' ) {
	rejudge('langid',$id);

	header('Location: ' . getBaseURI() . 'jury/language.php?id=' . urlencode($id) );
	exit;
}

echo "<h1>Language ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s', $id);

?>
<table>
<tr><td>ID:</td><td><?=htmlspecialchars($data['langid'])?></td></tr>
<tr><td>Name:</td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Extension:</td><td class="filename">.<?=htmlspecialchars($data['extension'])?></td></tr>
<tr><td>Allow submit:</td><td><?=printyn($data['allow_submit'])?></td></tr>
<tr><td>Allow judge:</td><td><?=printyn($data['allow_judge'])?></td></tr>
<tr><td>Timefactor:</td><td><?=(int)$data['time_factor']?></td></tr>
</table>

<form action="language.php" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" REJUDGE ALL! " />
</p>
</form>

<h2>Submissions in <?=htmlspecialchars($id)?></h2>

<?php
putSubmissions('langid', $id, TRUE);

require('../footer.php');
