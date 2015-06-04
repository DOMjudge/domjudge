<?php
/**
 * Show, edit and save files in an executable.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

// store files
if ( isset($_POST['storeid']) ) {
	$id = $_POST['storeid'];
	$executable = $DB->q('MAYBETUPLE SELECT * FROM executable
	                      WHERE execid = %s', $id);
	if ( empty($executable) ) error ("Executable $id not found");
	if ( !($tmpfname = tempnam(TMPDIR, "/executable-")) ) {
		error("failed to create temporary file");
	}
	if ( FALSE === file_put_contents($tmpfname, $executable['zipfile']) ) {
		error("failed to write zip file to temporary file");
	}
	$tmpexecdir = system("mktemp -d --tmpdir=" . TMPDIR, $retval);
	if ( $retval!=0 ) {
		error("failed to create temporary directory");
	}
	chmod($tmpexecdir, 0700);
	system("unzip -q $tmpfname -d '$tmpexecdir'", $retval);
	if ( $retval!=0 ) {
		error("Could not unzip executable to temporary directory.");
	}

	$zip = openZipFile($tmpfname);
	for ($j = 0; $j < $zip->numFiles; $j++) {
		if ( isset($_POST['skipped'][$j]) ) {
			// this file was skipped before
			continue;
		}
		$filename = $zip->getNameIndex($j);

		// overwrite it
		if ( FALSE === file_put_contents($tmpexecdir . "/" . $filename, str_replace("\r\n", "\n", $_POST['texta' . $j])) ) {
			error("Could not overwrite zip file contents.");
		}
	}
	$zip->close();

	system("zip -r -j $tmpfname '$tmpexecdir'", $retval);
	if ( $retval!=0 ) {
		error("failed to zip executable files.");
	}
	$content = file_get_contents($tmpfname . ".zip");

	$DB->q('UPDATE executable SET zipfile = %s, md5sum = %s WHERE execid = %s', $content, md5($content), $id);

	unlink($tmpfname);
	unlink($tmpfname . ".zip");
	system("rm -rf '$tmpexecdir'");

	header('Location: executable.php?id=' . $id);
	exit;
}

$id = getRequestID(FALSE);
$executable = $DB->q('MAYBETUPLE SELECT * FROM executable
                      WHERE execid = %s', $id);
if ( empty($executable) ) error ("Executable $id not found");

// Download was requested
if ( isset($_GET['fetch']) ) {
	error("downloading of single files not implemented yet");
}

$title = "Executable: $id";
require(LIBWWWDIR . '/header.php');

$edit_mode = ( isset($_GET['edit_source']) );

echo '<h2>' . ( $edit_mode ? 'Edit content of e' : 'E' ) . "xecutable " .htmlspecialchars($id). "</h2>\n\n";

if ( $edit_mode ) {
	echo addForm($pagename, 'post', null, 'multipart/form-data');
}

$html = '<script type="text/javascript" src="../js/tabber.js"></script>' .
	'<script src="../js/ace/ace.js" type="text/javascript" charset="utf-8"></script>' .
	'<script src="../js/ace/ext-modelist.js" type="text/javascript" charset="utf-8"></script>' .
	'<div class="tabber">';
if ( !($tmpfname = tempnam(TMPDIR, "/executable-")) ) {
	error("failed to create temporary file");
}
if ( FALSE === file_put_contents($tmpfname, $executable['zipfile']) ) {
	error("failed to write zip file to temporary file");
}
$zip = openZipFile($tmpfname);
$skippedBinary = array();
for ($j = 0; $j < $zip->numFiles; $j++) {
	$filename = $zip->getNameIndex($j);
	if ($filename[strlen($filename)-1] == "/") {
		if ( $edit_mode ) {
			echo addHidden("skipped[$j]", 1);
		}
		continue; // skip directory entries
	}
        $content = $zip->getFromIndex($j);
	if (!mb_check_encoding($content, 'ASCII')) {
		$skippedBinary[] = $filename;
		if ( $edit_mode ) {
			echo addHidden("skipped[$j]", 1);
		}
		continue; // skip binary files
	}

	$html .= '<div class="tabbertab' . ((int) @$_GET['rank'] === $j ? ' tabbertabdefault' : '') .'">' .
		'<h2 class="filename"><a id="source' . $j . '"></a>' .
		htmlspecialchars($filename) . "</h2>\n\n";
	// FIXME: skip files based on size?
	if ( $edit_mode ) {
		$html .= addTextArea('texta'. $j, $content, 120, 40) . "<br/>\n";
	} else {
		$html .= "<a href=\"show_executable.php?id=" . urlencode($id) . "&amp;fetch=" . $j . "\">" .
			"<img class=\"picto\" src=\"../images/b_save.png\" alt=\"download\" title=\"download\" /></a> " .
			"<a href=\"show_executable.php?edit_source=1&id=" . urlencode($id) . "&amp;rank=" . $j . "\">" .
			"<img class=\"picto\" src=\"../images/edit.png\" alt=\"edit\" title=\"edit\" />" .
			"</a>\n\n";
	}

	$html .= '<div class="editor" id="editor' . $j . '">' . htmlspecialchars($content) . '</div>';
	$html .= '<script>' . "\n";

	if ( $edit_mode ) {
		$html .= 'var textarea = document.getElementById("texta' . $j . '");' . "\n"
		       . 'textarea.style.display = \'none\';' . "\n";
	}
	$html .= 'var editor' .$j. ' = ace.edit("editor' . $j . '");' . "\n" .
		'editor' .$j. '.setTheme("ace/theme/eclipse");' . "\n" .
		'editor' .$j. '.setOptions({ maxLines: Infinity });';

	if ( $edit_mode ) {
		$html .= 'editor' .$j. '.getSession().setValue(textarea.value);' .
			'editor' .$j. '.getSession().on(\'change\', function(){' .
			'var textarea = document.getElementById("texta' . $j . '");' .
			'textarea.value = editor' .$j. '.getSession().getValue();' .
			'});';
	}
	$html .= 'function modefunc' . $j . '() {' . "\n" .
		'    var modelist = ace.require(\'ace/ext/modelist\');' . "\n" .
		'    var filePath = "' . $filename . '";' . "\n" .
		'    var mode = modelist.getModeForPath(filePath).mode;' . "\n" .
		'    editor' .$j. '.getSession().setMode(mode);' . "\n" .
		'    editor' .$j. '.setReadOnly(' . ( isset($_GET['edit_source']) ? 'false' : 'true' ) . ');' . "\n" .
		'};' . ' modefunc' . $j . '();' . "\n" .
		'</script>';

	$html .= '</div>';
}
$html .= "</div>";

if ( count($skippedBinary) > 0 ) {
	echo "binary files:\n";
	echo "<ul>";
	foreach ($skippedBinary as $skipped) {
		echo "<li>" . htmlspecialchars($skipped) . "</li>";
	}
	echo "</ul>";
}
echo $html;

if ( $edit_mode ) {
	echo addHidden('storeid', $id);
	echo addSubmit('save');

	echo addEndForm();
}

require(LIBWWWDIR . '/footer.php');
