<?php
/**
 * View an executable
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = getRequestID(FALSE);
$title = ucfirst((empty($_GET['cmd']) ? '' : htmlspecialchars($_GET['cmd']) . ' ') .
                 'executable' . ($id ? ' '.htmlspecialchars(@$id) : ''));

if ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.$pagename.'?id='.urlencode($id);
}

if ( isset($_GET['fetch']) ) {
	$filename = $id . ".zip";

	$size = $DB->q("MAYBEVALUE SELECT OCTET_LENGTH(zipfile)
	                FROM executable WHERE execid = %s",
	               $id);

	// sanity check before we start to output headers
	if ( $size===NULL || !is_numeric($size) ) {
		error("Problem while fetching executable");
	}

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
			$prop_file = 'domjudge-executable.ini';
			$newid = $_FILES['executable_archive']['name'][$fileid];
			$newid = substr($newid, 0, strlen($newid) - strlen(".zip"));
			$desc = $newid;
			$type = 'unknown';
			if ( isset($_POST['type']) ) {
				$type = $_POST['type'];
			}
			if ( !empty($id) ) {
				$desc = $DB->q('VALUE SELECT description FROM executable WHERE execid=%s', $id);
				$type = $DB->q('VALUE SELECT type FROM executable WHERE execid=%s', $id);
			}
			$ini_array = parse_ini_string($zip->getFromName($prop_file));
			if ( !empty($ini_array) ) {
				$newid = $ini_array['execid'];
				$desc = $ini_array['description'];
				$type = $ini_array['type'];
			}
			$content = file_get_contents($_FILES['executable_archive']['tmp_name'][$fileid]);
			if ( !empty($id) ) {
				$DB->q('UPDATE executable SET description=%s, md5sum=%s, zipfile=%s, type=%s
				        WHERE execid=%s',
				       $desc, md5($content), $content, $type, $id);
				$newid = $id;
			} else {
				$DB->q('INSERT INTO executable (execid, description, md5sum, zipfile, type)
				        VALUES (%s, %s, %s, %s, %s)',
				       $newid, $desc, md5($content), $content, $type);
			}
			$zip->close();
			auditlog('executable', $id, 'upload zip',
			         $_FILES['executable_archive']['name'][$fileid]);
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

require(LIBWWWDIR . '/header.php');

if ( !empty($cmd) ):

	requireAdmin();

	echo "<h2>$title</h2>\n\n";

	echo addForm('edit.php', 'post', null, 'multipart/form-data');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Executable ID:</td><td class=\"exec\">";
		$row = $DB->q('TUPLE SELECT execid, description, md5sum, type,
		               OCTET_LENGTH(zipfile) AS size
		               FROM executable
		               WHERE execid = %s', $id);
		echo addHidden('keydata[0][execid]', $row['execid']);
		echo htmlspecialchars($row['execid']);
	} else {
		echo "<tr><td><label for=\"data_0__execid_\">Executable ID:</label></td><td>";
		echo addInput('data[0][execid]', null, 8, 10,
		              " required pattern=\"" . IDENTIFIER_CHARS . "+\"");
		echo " (alphanumerics only)";
	}
	echo "</td></tr>\n";

// FIXME: unzip and show zip here
?>
<tr><td><label for="data_0__description_">Executable description:</label></td>
<td><?php echo addInput('data[0][description]', @$row['description'], 30, 255, 'required')?></td></tr>
<tr><td><label for="data_0__type_">Executable type:</label></td>
<td><?php echo addSelect('data[0][type]', $executable_types, @$row['type'], True)?></td></tr>
<tr><td>Content:     </td><td><a href="show_executable.php?edit_source=1&amp;id=<?php echo htmlspecialchars($id)?>">edit file contents</a></td></tr>
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
	addHidden('id', @$row['execid']) .
	'<label for="executable_archive__">Upload executable archive:</label>' .
	($cmd == 'add' ? addSelect('type', $executable_types) : '') .
	addFileField('executable_archive[]') .
	addSubmit('Upload', 'upload') .
	addEndForm();
}

require(LIBWWWDIR . '/footer.php');
exit;

endif;

$data = $DB->q('MAYBETUPLE SELECT execid, description, md5sum, type,
                                  OCTET_LENGTH(zipfile) AS size
                FROM executable WHERE execid = %s', $id);

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
<tr><td>type:        </td><td><?php echo htmlspecialchars($data['type'])?></td></tr>
<tr><td>size:        </td><td><?php echo htmlspecialchars($data['size'])?> Bytes</td></tr>
<tr><td>content:     </td><td><a href="show_executable.php?id=<?php echo htmlspecialchars($id)?>">view file contents</a></td></tr>
<tr><td>used as <?=$data['type'] ?> script:</td><td>
<?php
if ( $data['type'] == 'compare' ) {
	$res = $DB->q('SELECT probid AS id FROM problem
	               WHERE special_compare = %s ORDER BY probid', $data['execid']);
	$page = "problem";
	$prefix = "p";
} else if ( $data['type'] == 'compile' ) {
	$res = $DB->q('SELECT langid AS id FROM language
	               WHERE compile_script = %s ORDER BY langid', $data['execid']);
	$page = "language";
	$prefix = "";
} else if ( $data['type'] == 'run' ) {
	$res = $DB->q('SELECT probid AS id FROM problem
	               WHERE special_run = %s ORDER BY probid', $data['execid']);
	$page = "problem";
	$prefix = "p";
}
$used = FALSE;
if ( ($data['type'] == 'compare' || $data['type'] == 'run') &&
     dbconfig_get('default_'.$data['type']) == $data['execid'] ) {
	$used = TRUE;
	echo '<em>default ' . $data['type'] . '</em> ';
}
while( $row = $res->next() ) {
	$used = TRUE;
	echo '<a href="' . $page . '.php?id=' . $row['id'] . '">'
	    . $prefix . $row['id'] . '</a> ';
}
if ( ! $used ) echo "<span class=\"nodata\">none</span>";

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

if ( IS_ADMIN ) {
	echo "<p>" .
		'<a href="executable.php?fetch&amp;id=' . urlencode($id) .
		'"><img src="../images/b_save.png" ' .
		' title="export executable as zip-file" alt="export" /></a>' .
		editLink('executable',$id) . "\n" .
		delLink('executable','execid', $id) . "</p>\n\n";
}

require(LIBWWWDIR . '/footer.php');
