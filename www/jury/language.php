<?php
/**
 * View a language
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');

$id = @$_REQUEST['id'];
$title = 'Language '.htmlspecialchars(@$id);

if ( ! preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id) ) error("Invalid language id");

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
}

if ( !empty($pcmd) ) {

	if ( empty($id) ) error("Missing language id");

	if ( isset($pcmd['toggle_submit']) ) {
		$DB->q('UPDATE language SET allow_submit = %i WHERE langid = %s',
		       $_POST['val']['toggle_submit'], $id);
		auditlog('language', $id, 'set allow submit', $_POST['val']['toggle_submit']);
	}

	if ( isset($pcmd['toggle_judge']) ) {
		$DB->q('UPDATE language SET allow_judge = %i WHERE langid = %s',
		       $_POST['val']['toggle_judge'], $id);
		auditlog('language', $id, 'set allow judge', $_POST['val']['toggle_judge']);
	}
}

require(LIBWWWDIR . '/header.php');

if ( IS_ADMIN && !empty($cmd) ):

	echo "<h2>" . htmlspecialchars(ucfirst($cmd)) . " language</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Language ID/ext:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][langid]', $row['langid']);
		echo htmlspecialchars($row['langid']);
	} else {
		echo "<tr><td><label for=\"data_0__langid_\">Language ID/ext:</label></td><td>";
		echo addInput('data[0][langid]', null, 8, 8);
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__name_">Language name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 20, 255)?></td></tr>

<tr><td>Allow submit:</td>
<td><?php echo addRadioButton('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?php echo addRadioButton('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?php echo addRadioButton('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?php echo addRadioButton('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>

<tr><td><label for="data_0__time_factor_">Time factor:</label></td>
<td><?php echo addInput('data[0][time_factor]', @$row['time_factor'], 5, 5)?> x</td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','language') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s', $id);

if ( ! $data ) error("Missing or invalid language id");

echo "<h1>Language ".htmlspecialchars($id)."</h1>\n\n";

echo addForm($pagename) . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";

?>
<table>
<tr><td scope="row">ID/extension:</td><td><?php echo htmlspecialchars($data['langid'])?></td></tr>
<tr><td scope="row">Name:        </td><td><?php echo htmlspecialchars($data['name'])?></td></tr>
<tr><td scope="row">Allow submit:</td><td><?php echo printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this language?')"); ?>
</td></tr>
<tr><td scope="row">Allow judge: </td><td><?php echo printyn($data['allow_judge']) . ' ' .
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this language?')"); ?>
</td></tr>
<tr><td scope="row">Time factor:  </td><td><?php echo htmlspecialchars($data['time_factor'])?> x</td></tr>
</table>

<?php
echo addEndForm();

echo "<br />\n" . rejudgeForm('language',$data['langid']) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('language', $data['langid']) . "\n" .
		delLink('language','langid',$data['langid']) . "</p>\n\n";
}
echo "<h2>Submissions in " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'langid' => $id );
putSubmissions($cdata, $restrictions);

require(LIBWWWDIR . '/footer.php');
