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

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td class=\"probid\">";
		$row = $DB->q('TUPLE SELECT p.*, COUNT(testcaseid) AS testcases
		               FROM problem p
		               LEFT JOIN testcase USING (probid)
		               WHERE probid = %s
			       GROUP BY probid', $_GET['id']);
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
<td><?php echo addInput('data[0][name]', @$row['name'], 30, 255)?></td></tr>

<tr><td>Allow submit:</td>
<td><?php echo addRadioButton('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?php echo addRadioButton('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?php echo addRadioButton('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?php echo addRadioButton('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>
<?php
    if ( !empty($row['probid']) ) {
		echo '<tr><td>Testcases:</td><td>' .
			$row['testcases'] . ' <a href="testcase.php?probid=' .
			urlencode($row['probid']) . "\">details/edit</a></td></tr>\n";
	}
?>
<tr><td><label for="data_0__timelimit_">Timelimit:</label></td>
<td><?php echo addInput('data[0][timelimit]', @$row['timelimit'], 5, 5)?> sec</td></tr>

<tr><td><label for="data_0__color_">Balloon colour:</label></td>
<td><?php echo addInput('data[0][color]', @$row['color'], 8, 25)?>
<a target="_blank"
href="http://www.w3schools.com/css/css_colornames.asp"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__special_run_">Special run script:</label></td>
<td><?php echo addInput('data[0][special_run]', @$row['special_run'], 30, 25)?></td></tr>

<tr><td><label for="data_0__special_compare_">Special compare script:</label></td>
<td><?php echo addInput('data[0][special_compare]', @$row['special_compare'], 30, 25)?></td></tr>

<?php if (!empty($row['probid']) && class_exists("ZipArchive")): ?>
<tr><td><label for="data_0__archive_">Upload problem archive:</label></td>
<td><?php echo addFileField('data[0][archive]')?></td></tr>
<?php endif; ?>
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

$data = $DB->q('TUPLE SELECT p.*, c.contestname, count(rank) AS ntestcases
                FROM problem p
                NATURAL JOIN contest c
                LEFT JOIN testcase USING (probid)
                WHERE probid = %s GROUP BY probid', $id);

echo addForm($pagename) . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";
?>
<table>
<tr><td scope="row">ID:          </td><td class="probid"><?php echo htmlspecialchars($data['probid'])?></td></tr>
<tr><td scope="row">Name:        </td><td><?php echo htmlspecialchars($data['name'])?></td></tr>
<tr><td scope="row">Contest:     </td><td><?php echo htmlspecialchars($data['contestname']) .
									' (c' . htmlspecialchars($data['cid']) .')'?></td></tr>
<tr><td scope="row">Allow submit:</td><td class="nobreak"><?php echo printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this problem?')"); ?>
</td></tr>
<tr><td scope="row">Allow judge: </td><td><?php echo printyn($data['allow_judge']) . ' '.
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this problem?')"); ?>
</td></tr>
<tr><td scope="row">Testcases:   </td><td><?php
    if ( $data['ntestcases']==0 ) {
		echo '<em>no testcases</em>';
	} else {
		echo (int)$data['ntestcases'];
	}
	echo ' <a href="testcase.php?probid='.urlencode($data['probid']).'">details/edit</a>';
?></td></tr>
<tr><td scope="row">Timelimit:   </td><td><?php echo (int)$data['timelimit']?> sec</td></tr>
<?php
if ( !empty($data['color']) ) {
	echo '<tr><td scope="row">Colour:</td><td><img style="background-color: ' .
		htmlspecialchars($data['color']) .
		';" alt="problem colour ' . htmlspecialchars($data['color']) .
		'" src="../images/circle.png" /> ' . htmlspecialchars($data['color']) .
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
putSubmissions($cdata, $restrictions);

require(LIBWWWDIR . '/footer.php');
