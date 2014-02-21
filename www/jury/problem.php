<?php
/**
 * View a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = @$_REQUEST['id'];
$title = 'Problem '.htmlspecialchars(@$id);

if ( ! preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id) ) error("Invalid problem id");

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id);
}

if ( !empty($pcmd) ) {

	if ( empty($id) ) error("Missing problem id");

	if ( isset($pcmd['toggle_submit']) ) {
		$DB->q('UPDATE problem SET allow_submit = %i WHERE probid = %s',
			   $_POST['val']['toggle_submit'], $id);
		auditlog('problem', $id, 'set allow submit', $_POST['val']['toggle_submit']);
	}

	if ( isset($pcmd['toggle_judge']) ) {
		$DB->q('UPDATE problem SET allow_judge = %i WHERE probid = %s',
			   $_POST['val']['toggle_judge'], $id);
		auditlog('problem', $id, 'set allow judge', $_POST['val']['toggle_judge']);
	}

	if ( isset($pcmd['delete_text']) ) {
		$DB->q('UPDATE problem SET problemtext = NULL, problemtext_type = NULL
		        WHERE probid = %s', $id);
		auditlog('problem', $id, 'delete problem text');
	}
}
if ( isset($_POST['upload']) ) {
	if ( !empty($_FILES['problem_archive']['tmp_name'][0]) ) {
		foreach($_FILES['problem_archive']['tmp_name'] as $fileid => $tmpname) {
			checkFileUpload( $_FILES['problem_archive']['error'][$fileid] );
			$zip = openZipFile($_FILES['problem_archive']['tmp_name'][$fileid]);
			$newid = importZippedProblem($zip, empty($id) ? NULL : $id);
			$zip->close();
			auditlog('problem', $newid, 'upload zip', $_FILES['problem_archive']['name'][$fileid]);
		}
		if ( count($_FILES['problem_archive']['tmp_name']) == 1 ) {
			header('Location: '.$pagename.'?id='.urlencode((empty($newid)?$id:$newid)));
		} else {
			header('Location: problems.php');
		}
	} else {
		error("Missing filename for problem upload");
	}
}

// This doesn't return, call before sending headers
if ( isset($cmd) && $cmd == 'viewtext' ) putProblemText($id);

$jscolor=true;

require(LIBWWWDIR . '/header.php');

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>" .  htmlspecialchars(ucfirst($cmd)) . " problem</h2>\n\n";

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td class=\"probid\">";
		$row = $DB->q('TUPLE SELECT p.probid,p.cid,p.name,p.allow_submit,p.allow_judge,
	                                    p.timelimit,p.special_run,p.special_compare,p.color,
	                                    COUNT(testcaseid) AS testcases
		               FROM problem p
		               LEFT JOIN testcase USING (probid)
		               WHERE probid = %s GROUP BY probid', $id);
		echo addHidden('keydata[0][probid]', $row['probid']);
		echo htmlspecialchars($row['probid']);
	} else {
		echo "<tr><td><label for=\"data_0__probid_\">Problem ID:</label></td><td>";
		echo addInput('data[0][probid]', null, 8, 10, " required pattern=\"" . IDENTIFIER_CHARS . "+\"");
		echo " (alphanumerics only)";
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__cid_">Contest:</label></td>
<td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT cid,contestname FROM contest ORDER BY cid DESC");
echo addSelect('data[0][cid]', $cmap, @$row['cid'], true);
?>
</td></tr>

<tr><td><label for="data_0__name_">Problem name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 30, 255, 'required')?></td></tr>

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
<td><?php echo addInputField('number','data[0][timelimit]', @$row['timelimit'],
	' min="1" max="10000" required')?> sec</td></tr>

<tr><td><label for="data_0__color_">Balloon colour:</label></td>
<td><?php echo addInputField('color','data[0][color]', @$row['color'],
	' class="color {required:false,adjust:false,hash:true,caps:false}"')?>
<a target="_blank"
href="http://www.w3schools.com/cssref/css_colornames.asp"><img
src="../images/b_help.png" class="smallpicto" alt="?" /></a></td></tr>

<tr><td><label for="data_0__problemtext_">Problem text:</label></td>
<td><?php echo addFileField('data[0][problemtext]', 30, ' accept="text/plain,text/html,application/pdf"')?></td></tr>

<tr><td><label for="data_0__special_run_">Special run script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable ORDER BY execid");
$execmap[''] = 'none';
echo addSelect('data[0][special_run]', $execmap, @$row['special_run'], True);
?>
</td></tr>

<tr><td><label for="data_0__special_compare_">Special compare script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable ORDER BY execid");
$execmap['float'] = 'float';
$execmap[''] = 'none';
echo addSelect('data[0][special_compare]', $execmap, @$row['special_compare'], True);
?>
</td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','problem') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	addEndForm();


if ( class_exists("ZipArchive") ) {
	echo "<br /><em>or</em><br /><br />\n" .
	addForm($pagename, 'post', null, 'multipart/form-data') .
	addHidden('id', @$row['probid']) .
	'<label for="problem_archive__">Upload problem archive:</label>' .
	addFileField('problem_archive[]') .
	addSubmit('Upload', 'upload') .
	addEndForm();
}

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('TUPLE SELECT p.probid,p.cid,p.name,p.allow_submit,p.allow_judge,
                             p.timelimit,p.special_run,p.special_compare,p.color,
                             p.problemtext_type,c.contestname, count(rank) AS ntestcases
                FROM problem p
                NATURAL JOIN contest c
                LEFT JOIN testcase USING (probid)
                WHERE probid = %s GROUP BY probid', $id);

if ( ! $data ) error("Missing or invalid problem id");

echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

echo addForm($pagename . '?id=' . urlencode($id),
             'post', null, 'multipart/form-data') . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";
?>
<table>
<tr><td>ID:          </td><td class="probid"><?php echo htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Name:        </td><td><?php echo htmlspecialchars($data['name'])?></td></tr>
<tr><td>Contest:     </td><td><?php echo htmlspecialchars($data['contestname']) .
									' (c' . htmlspecialchars($data['cid']) .')'?></td></tr>
<tr><td>Allow submit:</td><td class="nobreak"><?php echo printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this problem?')"); ?>
</td></tr>
<tr><td>Allow judge: </td><td><?php echo printyn($data['allow_judge']) . ' '.
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this problem?')"); ?>
</td></tr>
<tr><td>Testcases:   </td><td><?php
    if ( $data['ntestcases']==0 ) {
		echo '<em>no testcases</em>';
	} else {
		echo (int)$data['ntestcases'];
	}
	echo ' <a href="testcase.php?probid='.urlencode($data['probid']).'">details/edit</a>';
?></td></tr>
<tr><td>Timelimit:   </td><td><?php echo (int)$data['timelimit']?> sec</td></tr>
<?php
if ( !empty($data['color']) ) {
	echo '<tr><td>Colour:</td><td><div class="circle" style="background-color: ' .
		htmlspecialchars($data['color']) .
		';"></div> ' . htmlspecialchars($data['color']) .
		"</td></tr>\n";
}
if ( !empty($data['problemtext_type']) ) {
	echo '<tr><td>Problem text:</td><td class="nobreak"><a href="problem.php?id=' .
	    urlencode($id) . '&amp;cmd=viewtext"><img src="../images/' .
	    urlencode($data['problemtext_type']) . '.png" alt="problem text" ' .
	    'title="view problem description" /></a> ' .
	    addSubmit('delete', 'cmd[delete_text]',
	              "return confirm('Delete problem description text?')") .
	    "</td></tr>\n";
}
if ( !empty($data['special_compare']) ) {
	echo '<tr><td>Special run script:</td><td class="filename">' .
		'<a href="executable.php?id=' . urlencode($data['special_run']) . '">' .
		htmlspecialchars($data['special_run']) . "</a></td></tr>\n";
}
if ( !empty($data['special_compare']) ) {
	echo '<tr><td>Special compare script:</td><td class="filename">' .
		'<a href="executable.php?id=' . urlencode($data['special_compare']) . '">' .
		htmlspecialchars($data['special_compare']) . "</a></td></tr>\n";
}

if ( IS_ADMIN && class_exists("ZipArchive") ) {
	echo '<tr>' .
		'<td>Problem archive:</td>' .
		'<td>' . addFileField('problem_archive[]') .
		addSubmit('Upload', 'upload') . '</td>' .
		"</tr>\n";
}

echo "</table>\n" . addEndForm();

echo "<br />\n" . rejudgeForm('problem', $id) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		exportLink($id) . "\n" .
		editLink('problem',$id) . "\n" .
		delLink('problem','probid', $id) . "</p>\n\n";
}

echo "<h2>Submissions for " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'probid' => $id );
putSubmissions($cdata, $restrictions);

require(LIBWWWDIR . '/footer.php');
