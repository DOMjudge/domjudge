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
require('../forms.php');

if ( IS_ADMIN && !empty($cmd) ):
	
	echo "<h2>" . ucfirst($cmd) . " problem</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM problem WHERE probid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][probid]', $row['probid']);
		echo htmlspecialchars($row['probid']);
	} else {
		echo "<tr><td><label for=\"data_0__probid_\">Problem ID:</label></td><td>";
		echo addInput('data[0][probid]', null, 8, 10);
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__cid_">Contest:</label></td>
<td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT cid,contestname FROM contest ORDER BY cid");
echo addSelect('data[0][cid]', $cmap, @$row['cid'], true);
?>
</td></tr>

<tr><td><label for="data_0__name_">Problem name:</label></td>
<td><?=addInput('data[0][name]', @$row['name'], 20, 255)?></td></tr>

<tr><td>Allow submit:</td>
<td><?=addRadioBox('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?=addRadioBox('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?=addRadioBox('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?=addRadioBox('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>

<tr><td><label for="data_0__testdata_">Path to testdata:</label></td>
<td><?=addInput('data[0][testdata]', @$row['testdata'], 20, 255)?></td></tr>

<tr><td><label for="data_0__timelimit_">Timelimit:</label></td>
<td><?=addInput('data[0][timelimit]', @$row['timelimit'], 5, 5)?> sec</td></tr>

<tr><td><label for="data_0__color_">Balloon colour:</label></td>
<td><?=addInput('data[0][color]', @$row['color'], 8, 10)?>
<a href="http://www.w3schools.com/css/css_colornames.asp" target="_blank"><small>(help)</small></a></td></tr>

<tr><td><label for="data_0__special_run_">Special run script:</label></td>
<td><?=addInput('data[0][special_run]', @$row['special_run'], 10, 8)?></td></tr>

<tr><td><label for="data_0__special_compare_">Special compare script:</label></td>
<td><?=addInput('data[0][special_compare]', @$row['special_compare'], 10, 8)?></td></tr>
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

echo addForm($pagename) . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";
?>
<table>
<tr><td scope="row">ID:          </td><td><?=htmlspecialchars($data['probid'])?></td></tr>
<tr><td scope="row">Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td scope="row">Contest:     </td><td><?=htmlspecialchars($data['cid']).' - '.
                                 htmlentities($data['contestname'])?></td></tr>
<tr><td scope="row">Allow submit:</td><td class="nobreak"><?=printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this problem?')"); ?>
</td></tr>
<tr><td scope="row">Allow judge: </td><td><?=printyn($data['allow_judge']) . ' '.
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this problem?')"); ?>
</td></tr>
<tr><td scope="row">Testdata:    </td><td class="filename"><?=htmlspecialchars($data['testdata'])?></td></tr>
<tr><td scope="row">Timelimit:   </td><td><?=(int)$data['timelimit']?></td></tr>
<?php
if ( isset($data['color']) ) {
	echo '<tr><td scope="row">Colour:       </td><td style="background: ' .
		htmlspecialchars($data['color']) .
		';">' . htmlspecialchars($data['color']) . "</td></tr>\n";
}
if ( isset($data['special_run']) ) {
	echo '<tr><td scope="row">Special run script:</td><td class="filename">' .
		htmlspecialchars($data['special_run']) . "</td></tr>\n";
}
if ( isset($data['special_compare']) ) {
	echo '<tr><td scope="row">Special compare script:</td><td class="filename">' .
		htmlspecialchars($data['special_compare']) . "</td></tr>\n";
}

echo "</table>\n" .
	addEndForm();

echo "<p>" . rejudgeForm('problem', $id) . "</p>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . editLink('problem',$id) . "\n" .
		delLink('problem','probid', $id) . "</p>\n\n";
}

echo "<h2>Submissions for " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'probid' => $id );
putSubmissions($restrictions, TRUE);

require('../footer.php');
