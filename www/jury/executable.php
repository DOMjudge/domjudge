<?php
/**
 * View an executable
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = @$_REQUEST['id'];
$title = 'Executable '.htmlspecialchars(@$id);

if ( ! preg_match('/^' . IDENTIFIER_CHARS . '*$/', $id) ) error("Invalid executable id");

if ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id);
}

if ( isset($_GET['fetch']) ) {
	$filename = $id . "-script.zip";

	$size = $DB->q("MAYBEVALUE SELECT OCTET_LENGTH(zipfile)
	                FROM executable WHERE execid = %s",
	               $id);

	// sanity check before we start to output headers
	if ( $size===NULL || !is_numeric($size)) error("Problem while fetching executable");

	header("Content-Type: application/zip; name=\"$filename\"");
	header("Content-Disposition: attachment; filename=\"$filename\"");
	header("Content-Length: $size");

	echo $DB->q("VALUE SELECT SQL_NO_CACHE zipfile FROM executable
	             WHERE execid = %s", $id);

	exit(0);
}

if ( isset($_POST['upload']) ) {
	if ( !empty($_FILES['executable_archive']['tmp_name'][0]) ) {
		foreach($_FILES['executable_archive']['tmp_name'] as $fileid => $tmpname) {
			checkFileUpload( $_FILES['executable_archive']['error'][$fileid] );
			$zip = openZipFile($_FILES['executable_archive']['tmp_name'][$fileid]);
			global $DB;
			$prop_file = 'domjudge-executable.ini';
			$newid = $_FILES['executable_archive']['name'][$fileid];
			$newid = substr($newid, 0, strlen($newid) - strlen(".zip"));
			$desc = $newid;
			if ( isset($id) ) {
				$desc = $DB->q('VALUE SELECT description FROM executable WHERE execid=%s', $id);
			}
			$ini_array = parse_ini_string($zip->getFromName($prop_file));
			if ( !empty($ini_array) ) {
				$newid = $ini_array['execid'];
				$desc = $ini_array['description'];
			}
			if ( $zip->getFromName('build') === FALSE ) {
				error("Need 'build' script/executable when adding a new executable.");
			}
			$content = file_get_contents($_FILES['executable_archive']['tmp_name'][$fileid]);
			if ( isset($id) ) {
				$DB->q("UPDATE executable SET description=%s, md5sum=%s, zipfile=%s" .
					" WHERE execid=%s",
					$desc, md5($content), $content, $id);
				$newid = $id;
			} else {
				$DB->q("INSERT INTO executable (execid, description, md5sum, zipfile) " .
					"VALUES (%s, %s, %s, %s)",
					$newid, $desc, md5($content), $content);
			}
			$zip->close();
			auditlog('executable', $id, 'upload zip', $_FILES['executable_archive']['name'][$fileid]);
		}
		if ( count($_FILES['executable_archive']['tmp_name']) == 1 ) {
			header('Location: '.$pagename.'?id='.urlencode((empty($newid)?$id:$newid)));
		} else {
			header('Location: executables.php');
		}
	} else {
		error("Missing filename for executable upload");
	}
}

// This doesn't return, call before sending headers
// FIXME: add option to download executable
if ( isset($cmd) && $cmd == 'viewtext' ) putProblemText($id);

$jscolor=true;

require(LIBWWWDIR . '/header.php');

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>" .  htmlspecialchars(ucfirst($cmd)) . " executable</h2>\n\n";

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Executable ID:</td><td class=\"exec\">";
		$row = $DB->q('TUPLE SELECT execid, description, md5sum, OCTET_LENGTH(zipfile) AS size
		               FROM executable
		               WHERE execid = %s', $id);
		echo addHidden('keydata[0][execid]', $row['execid']);
		echo htmlspecialchars($row['execid']);
	} else {
		echo "<tr><td><label for=\"data_0__execid_\">Executable ID:</label></td><td>";
		echo addInput('data[0][exec]', null, 8, 10, " required pattern=\"" . IDENTIFIER_CHARS . "+\"");
		echo " (alphanumerics only)";
	}
	echo "</td></tr>\n";

// FIXME: unzip and show zip here
?>
<tr><td><label for="data_0__description_">Executable description:</label></td>
<td><?php echo addInput('data[0][description]', @$row['description'], 30, 255, 'required')?></td></tr>

</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','executable') .
	addHidden('referrer', @$_GET['referrer']) .
	addSubmit('Save') .
	addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
	addEndForm();


if ( class_exists("ZipArchive") ) {
	echo "<br /><em>or</em><br /><br />\n" .
	addForm($pagename, 'post', null, 'multipart/form-data') .
	addHidden('id', @$row['probid']) .
	'<label for="executable_archive__">Upload executable archive:</label>' .
	addFileField('executable_archive[]') .
	addSubmit('Upload', 'upload') .
	addEndForm();
}

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('TUPLE SELECT execid, description, md5sum, OCTET_LENGTH(zipfile) AS size
	       FROM executable
	       WHERE execid = %s', $id);

if ( ! $data ) error("Missing or invalid problem id");

echo "<h1>Executable ".htmlspecialchars($id)."</h1>\n\n";

echo addForm($pagename . '?id=' . urlencode($id),
             'post', null, 'multipart/form-data') . "<p>\n" .
	addHidden('id', $id) .
	"</p>\n";
?>
<table>
<tr><td>ID:          </td><td class="execid"><?php echo htmlspecialchars($data['execid'])?></td></tr>
<tr><td>Name:        </td><td><?php echo htmlspecialchars($data['description'])?></td></tr>
<tr><td>md5sum:      </td><td><?php echo htmlspecialchars($data['md5sum'])?></td></tr>
<tr><td>size:        </td><td><?php echo htmlspecialchars($data['size'])?> Bytes</td></tr>
<tr><td>content:        </td><td><a href="show_executable.php?id=<?php echo htmlspecialchars($id)?>">view content of zip file</a></td></tr>
<tr><td>used as compare script:</td><td>
<?php
$res = $DB->q('SELECT probid FROM problem WHERE special_compare = %s ORDER BY probid', $data['execid']);
if ( $res->count() > 0 ) {
	while( $row = $res->next() ) {
		echo '<a href="problem.php?id=' . $row['probid'] . '">'
			. $row['probid'] . '</a> ';
	}
} else {
	echo "<span class=\"nodata\">none</span>";
}

?>
</td></tr>
<?php
if ( IS_ADMIN && class_exists("ZipArchive") ) {
	echo '<tr>' .
		'<td>Executable archive:</td>' .
		'<td>' . addFileField('executable_archive[]') .
		addSubmit('Upload', 'upload') . '</td>' .
		"</tr>\n";
}

echo "</table>\n" . addEndForm();

echo "<br />\n" . rejudgeForm('executable', $id) . "\n\n"; // FIXME: useful?

if ( IS_ADMIN ) {
	echo "<p>" .
		'<a href="executable.php?fetch&id=' . urlencode($id) .
		'"><img src="../images/b_save.png" ' .
		' title="export executable as zip-file" alt="export" /></a>' .
		editLink('executable',$id) . "\n" .
		delLink('executable','execid', $id) . "</p>\n\n";
}

/* FIXME: useful?
echo "<h2>Submissions for " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'execid' => $id );
putSubmissions($cdata, $restrictions);
*/

require(LIBWWWDIR . '/footer.php');
