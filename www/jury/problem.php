<?php
/**
 * View a problem
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
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

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

if ( IS_ADMIN && !empty($cmd) ):
	
	echo "<h2>" . ucfirst($cmd) . " problem</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td class=\"probid\">";
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
<td><?=addInput('data[0][name]', @$row['name'], 30, 255)?></td></tr>

<tr><td>Allow submit:</td>
<td><?=addRadioButton('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?=addRadioButton('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?=addRadioButton('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?=addRadioButton('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>

<tr><td><label for="data_0__timelimit_">Timelimit:</label></td>
<td><?=addInput('data[0][timelimit]', @$row['timelimit'], 5, 5)?> sec</td></tr>

<tr><td><label for="data_0__color_">Balloon colour:</label></td>
<td><?=addInput('data[0][color]', @$row['color'], 8, 25)?>
<a target="_blank"
href="http://www.w3schools.com/css/css_colornames.asp"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__special_run_">Special run script:</label></td>
<td><?=addInput('data[0][special_run]', @$row['special_run'], 30, 25)?></td></tr>

<tr><td><label for="data_0__special_compare_">Special compare script:</label></td>
<td><?=addInput('data[0][special_compare]', @$row['special_compare'], 30, 25)?></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','problem') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel') .
	addEndForm();

require(LIBWWWDIR . '/footer.php');
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
<tr><td scope="row">ID:          </td><td class="probid"><?=htmlspecialchars($data['probid'])?></td></tr>
<tr><td scope="row">Name:        </td><td><?=htmlspecialchars($data['name'])?></td></tr>
<tr><td scope="row">Contest:     </td><td><?=htmlspecialchars($data['contestname']) .
									' (c' . htmlspecialchars($data['cid']) .')'?></td></tr>
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
<tr><td scope="row" valign="top">Testcase:    </td><td><?php
	$tc = $DB->q("MAYBETUPLE SELECT md5sum_input, md5sum_output FROM testcase WHERE probid = %s",
		$data['probid']);
	foreach(array('input','output') as $inout) {
		echo $inout . ": ";
		if ( $tc['md5sum_' . $inout] ) {
			echo htmlspecialchars($tc['md5sum_'.$inout]) . " ";
			if ( IS_ADMIN ) {
				echo '<a href="testcase.php?probid='.urlencode($data['probid']).'">details</a> | ';
			}
			echo "<a href=\"testcase.php?probid=" . urlencode($data['probid']) . "&amp;fetch=" .
				$inout . "\">download</a>";
		} else {
			echo '<a href="testcase.php?probid='.urlencode($data['probid']).'">add</a>';
		}
		echo "<br />\n";
	}

?></td></tr>
<tr><td scope="row">Timelimit:   </td><td><?=(int)$data['timelimit']?> sec</td></tr>
<?php
if ( !empty($data['color']) ) {
	echo '<tr><td scope="row">Colour:       </td><td><span style="color: ' .
		htmlspecialchars($data['color']) .
		';">' . BALLOON_SYM . '</span> ' . htmlspecialchars($data['color']) .
		"</td></tr>\n";
}
if ( !empty($data['special_run']) ) {
	echo '<tr><td scope="row">Special run script:</td><td class="filename">' .
		htmlspecialchars($data['special_run']) . "</td></tr>\n";
}
if ( !empty($data['special_compare']) ) {
	echo '<tr><td scope="row">Special compare script:</td><td class="filename">' .
		htmlspecialchars($data['special_compare']) . "</td></tr>\n";
}

echo "</table>\n" .
	addEndForm();

echo "<br />\n" . rejudgeForm('problem', $id) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . editLink('problem',$id) . "\n" .
		delLink('problem','probid', $id) . "</p>\n\n";
}

echo "<h2>Submissions for " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'probid' => $id );
putSubmissions($cdata, $restrictions, TRUE);

require(LIBWWWDIR . '/footer.php');
