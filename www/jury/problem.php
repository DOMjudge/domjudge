<?php
/**
 * View a problem
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID();
$current_cid = null;
if ( isset($_GET['cid']) && is_numeric($_GET['cid']) ) {
	$cid = $_GET['cid'];
	$cdata = $cdatas[$cid];
	$current_cid = $cid;
}
$title = 'Problem p'.htmlspecialchars(@$id);
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
                 'problem' . ($id ? ' p'.htmlspecialchars(@$id) : ''));

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} elseif ( isset($id) ) {
	$extra = '';
	if ( $current_cid !== null ) {
		$extra = '&cid=' . urlencode($current_cid);
	}
	$refresh = '15;url='.$pagename.'?id='.urlencode($id).$extra;
}

// This doesn't return, call before sending headers
if ( isset($cmd) && $cmd == 'viewtext' ) putProblemText($id);

require(LIBWWWDIR . '/header.php');

if ( isset($_POST['upload']) ) {
	if ( !empty($_FILES['problem_archive']['tmp_name'][0]) ) {
		foreach($_FILES['problem_archive']['tmp_name'] as $fileid => $tmpname) {
			$cid = $_POST['contest'];
			checkFileUpload( $_FILES['problem_archive']['error'][$fileid] );
			$zip = openZipFile($_FILES['problem_archive']['tmp_name'][$fileid]);
			$newid = importZippedProblem($zip, empty($id) ? NULL : $id, $cid);
			$zip->close();
			auditlog('problem', $newid, 'upload zip',
			         $_FILES['problem_archive']['name'][$fileid]);
		}
		if ( count($_FILES['problem_archive']['tmp_name']) == 1 ) {
			$probid = empty($newid) ? $id : $newid;
			$probname = $DB->q('VALUE SELECT name FROM problem
			                    WHERE probid = %i', $probid);

			echo '<p><a href="' . $pagename.'?id='.urlencode($probid) .
			    '">Return to problem p' . htmlspecialchars($probid) .
			    ': ' . htmlspecialchars($probname) . ".</a></p>\n";
		}
		echo "<p><a href=\"problems.php\">Return to problems overview.</a></p>\n";
	} else {
		error("Missing filename for problem upload. Maybe you have to increase upload_max_filesize, see config checker.");
	}

	require(LIBWWWDIR . '/footer.php');
	exit;
}

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>$title</h2>\n\n";

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Problem ID:</td><td>";
		$row = $DB->q('TUPLE SELECT p.probid,p.name,
		                            p.timelimit,p.memlimit,p.outputlimit,
		                            p.special_run,p.special_compare, p.special_compare_args,
		                            p.problemtext_type, COUNT(testcaseid) AS testcases
		               FROM problem p
		               LEFT JOIN testcase USING (probid)
		               WHERE probid = %i GROUP BY probid', $id);
		echo addHidden('keydata[0][probid]', $row['probid']);
		echo "p" . htmlspecialchars($row['probid']);
		echo "</td></tr>\n";
	}

?>
<tr><td><label for="data_0__name_">Problem name:</label></td>
<td><?php echo addInput('data[0][name]', @$row['name'], 30, 255, 'required')?></td></tr>

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

<tr><td><label for="data_0__memlimit_">Memory limit:</label></td>
<td><?php echo addInputField('number','data[0][memlimit]', @$row['memlimit']);
?> kB (leave empty for default)</td></tr>

<tr><td><label for="data_0__outputlimit_">Output limit:</label></td>
<td><?php echo addInputField('number','data[0][outputlimit]', @$row['outputlimit']);
?> kB (leave empty for default)</td></tr>

<tr><td><label for="data_0__problemtext_">Problem text:</label></td>
<td><?php
echo addFileField('data[0][problemtext]', 30, ' accept="text/plain,text/html,application/pdf"');
if ( !empty($row['problemtext_type']) ) {
	echo addCheckBox('unset[0][problemtext]') .
		'<label for="unset_0__problemtext_">delete</label>';
}
?></td></tr>

<tr><td><label for="data_0__special_run_">Run script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable
                   WHERE type = 'run' ORDER BY execid");
$execmap = array('' => 'default') + $execmap;
echo addSelect('data[0][special_run]', $execmap, @$row['special_run'], True);
?>
</td></tr>

<tr><td><label for="data_0__special_compare_">Compare script:</label></td>
<td>
<?php
$execmap = $DB->q("KEYVALUETABLE SELECT execid,description FROM executable
                   WHERE type = 'compare' ORDER BY execid");
$execmap = array('' => 'default') + $execmap;
echo addSelect('data[0][special_compare]', $execmap, @$row['special_compare'], True);
?>
</td></tr>

<tr><td><label for="data_0__special_compare_args_">Compare args:</label></td>
<td><?php echo addInput('data[0][special_compare_args]', @$row['special_compare_args'], 30, 255)?></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','problem') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	addEndForm();


if ( class_exists("ZipArchive") ) {
	$contests = $DB->q("KEYVALUETABLE SELECT cid, CONCAT('c', cid, ': ' , shortname, ' - ', name) FROM contest");
	$values = array(-1 => 'Do not add / update contest data');
	foreach ($contests as $cid => $contest) {
		$values[$cid] = $contest;
	}
	echo "<br /><em>or</em><br /><br />\n" .
	addForm($pagename, 'post', null, 'multipart/form-data') .
	addHidden('id', @$row['probid']) .
	'Contest: ' .
	addSelect('contest', $values, -1, true) .
	'<label for="problem_archive__">Upload problem archive:</label>' .
	addFileField('problem_archive[]') .
	addSubmit('Upload', 'upload') .
	addEndForm();
}

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('TUPLE SELECT p.probid,p.name,
                             p.timelimit,p.memlimit,p.outputlimit,
                             p.special_run,p.special_compare,p.special_compare_args,
                             p.problemtext_type, count(rank) AS ntestcases
                FROM problem p
                LEFT JOIN testcase USING (probid)
                WHERE probid = %i GROUP BY probid', $id);

if ( ! $data ) error("Missing or invalid problem id");

if ( !isset($data['memlimit']) ) {
	$defaultmemlimit = TRUE;
	$data['memlimit'] = dbconfig_get('memory_limit');
}
if ( !isset($data['outputlimit']) ) {
	$defaultoutputlimit = TRUE;
	$data['outputlimit'] = dbconfig_get('output_limit');
}
if ( !isset($data['special_run']) ) {
	$defaultrun = TRUE;
	$data['special_run'] = dbconfig_get('default_run');
}
if ( !isset($data['special_compare']) ) {
	$defaultcompare = TRUE;
	$data['special_compare'] = dbconfig_get('default_compare');
}

echo "<h1>Problem ".htmlspecialchars($data['name'])."</h1>\n\n";

echo addForm($pagename . '?id=' . urlencode($id),
             'post', null, 'multipart/form-data') . "<p>\n" .
	addHidden('id', $id) .
	"</p>\n";
?>
<table>
<tr><td>ID:          </td><td>p<?php echo htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Name:        </td><td><?php echo htmlspecialchars($data['name'])?></td></tr>
<tr><td>Testcases:   </td><td><?php
    if ( $data['ntestcases']==0 ) {
		echo '<em>no testcases</em>';
	} else {
		echo (int)$data['ntestcases'];
	}
	echo ' <a href="testcase.php?probid='.urlencode($data['probid']).'">details/edit</a>';
?></td></tr>
<tr><td>Timelimit:   </td><td><?php echo (int)$data['timelimit']?> sec</td></tr>
<tr><td>Memory limit:</td><td><?php	echo (int)$data['memlimit'].' kB'.(@$defaultmemlimit ? ' (default)' : '')?></td></tr>
<tr><td>Output limit:</td><td><?php echo (int)$data['outputlimit'].' kB'.(@$defaultoutputlimit ? ' (default)' : '')?></td></tr>
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
	    'title="view problem description" /></a> ' . "</td></tr>\n";
}

echo '<tr><td>Run script:</td><td class="filename">' .
	'<a href="executable.php?id=' . urlencode($data['special_run']) . '">' .
	htmlspecialchars($data['special_run']) . "</a>" .
	(@$defaultrun ? ' (default)' : '') . "</td></tr>\n";

echo '<tr><td>Compare script:</td><td class="filename">' .
	'<a href="executable.php?id=' . urlencode($data['special_compare']) . '">' .
	htmlspecialchars($data['special_compare']) . "</a>" .
	(@$defaultcompare ? ' (default)' : '') . "</td></tr>\n";

if ( !empty($data['special_compare_args']) ) {
	echo '<tr><td>Compare script arguments:</td><td>' .
		htmlspecialchars($data['special_compare_args']) . "</td></tr>\n";
}

echo "</table>\n" . addEndForm();

echo "<br />\n" . rejudgeForm('problem', $id) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		exportLink($id) . "\n" .
		editLink('problem',$id) . "\n" .
		delLink('problem','probid', $id) . "</p>\n\n";
}

if ( $current_cid === null) {
	echo "<h3>Contests</h3>\n\n";

	$res = $DB->q('TABLE SELECT c.*, cp.shortname AS problemshortname,
	                            cp.allow_submit, cp.allow_judge, cp.color
	               FROM contest c
	               INNER JOIN contestproblem cp USING (cid)
	               WHERE cp.probid = %i ORDER BY starttime DESC', $id);

	if ( count($res) == 0 ) {
		echo "<p class=\"nodata\">No contests defined</p>\n\n";
	}
	else {
		$times = array('activate', 'start', 'freeze', 'end', 'unfreeze');
		echo "<table class=\"list sortable\">\n<thead>\n" .
		     "<tr><th scope=\"col\" class=\"sorttable_numeric\">CID</th>";
		echo "<th scope=\"col\">contest<br />shortname</th>\n";
		echo "<th scope=\"col\">contest<br />name</th>";
		echo "<th scope=\"col\">problem<br />shortname</th>";
		echo "<th scope=\"col\">allow<br />submit</th>";
		echo "<th scope=\"col\">allow<br />judge</th>";
		echo "<th class=\"sorttable_nosort\" scope=\"col\">colour</th>\n";
		echo "</tr>\n</thead>\n<tbody>\n";

		$iseven = false;
		foreach ( $res as $row ) {

			$link = '<a href="contest.php?id=' . urlencode($row['cid']) . '">';

			echo '<tr class="' .
			     ($iseven ? 'roweven' : 'rowodd') .
			     (!$row['enabled'] ? ' disabled' : '') . '">' .
			     "<td class=\"tdright\">" . $link .
			     "c" . (int)$row['cid'] . "</a></td>\n";
			echo "<td>" . $link . htmlspecialchars($row['shortname']) . "</a></td>\n";
			echo "<td>" . $link . htmlspecialchars($row['name']) . "</a></td>\n";
			echo "<td>" . $link . htmlspecialchars($row['problemshortname']) . "</a></td>\n";
			echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_submit']) . "</a></td>\n";
			echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_judge']) . "</a></td>\n";
			echo ( !empty($row['color'])
				? '<td title="' . htmlspecialchars($row['color']) .
				  '">' . $link . '<div class="circle" style="background-color: ' .
				  htmlspecialchars($row['color']) .
				  ';"></div></a></td>'
				: '<td>'. $link . '&nbsp;</a></td>' );

			$iseven = !$iseven;

			echo "</tr>\n";
		}
		echo "</tbody>\n</table>\n\n";
	}
}

echo "<h2>Submissions for " . htmlspecialchars($data['name']) . "</h2>\n\n";

$restrictions = array( 'probid' => $id );
putSubmissions($cdatas, $restrictions);

require(LIBWWWDIR . '/footer.php');
