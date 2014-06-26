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
	$tmpexecdir = system("mktemp -d --tmpdir=$TMPDIR executable-XXXXXX", $retval);
	if ( $retval!=0 ) {
		error("failed to created temporary directory");
	}
	chmod($tmpexecdir, 0700);
	system("unzip -q $tmpfname -d $tmpexecdir", $retval);
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

	system("zip -r -j $tmpfname $tmpexecdir", $retval);
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

if ( isset($_GET['edit_source']) ) {
	echo '<h2><a id="source"></a>Edit content of executable ' .
		"<a href=\"executable.php?id=$id\">$id</a></h2>\n\n";

	echo addForm($pagename, 'post', null, 'multipart/form-data');
} else {
		echo "<h2>Executable zip file for " .htmlspecialchars($id). "</h2>";
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
		if ( isset($_GET['edit_source']) ) {
			echo addHidden("skipped[$j]", 1);
		}
		continue; // skip directory entries
	}
        $content = $zip->getFromIndex($j);
	if (!mb_check_encoding($content, 'ASCII')) {
		$skippedBinary[] = $filename;
		if ( isset($_GET['edit_source']) ) {
			echo addHidden("skipped[$j]", 1);
		}
		continue; // skip binary files
	}
	// FIXME: skip files based on size?
	// FIXME: use a common function to view syntax highlighted files in combination with tabbed view
	if ( isset($_GET['edit_source']) ) {
		$html .= '<div class="tabbertab' . ((int)$_GET['rank'] === $j ? ' tabbertabdefault' : '') .'">' .
			'<h2 class="filename"><a id="source' . $j . '"></a>' .
			htmlspecialchars($filename) . "</h2>\n\n";

		$html .= addTextArea('texta'. $j, $content, 120, 40) . "<br/>\n" .
			'<div class="editor" id="editor' . $j . '">'
			. htmlspecialchars($content) . '</div>' .
			'<script>' . "\n" .
			'var textarea = document.getElementById("texta' . $j . '");' . "\n" .
			'textarea.style.display = \'none\';' . "\n" .
			'var editor' .$j. ' = ace.edit("editor' . $j . '");' . "\n" .
			'editor' .$j. '.setTheme("ace/theme/eclipse");' . "\n" .
			'editor' .$j. '.setOptions({ maxLines: Infinity });' .
			'editor' .$j. '.getSession().setValue(textarea.value);' .
			'editor' .$j. '.getSession().on(\'change\', function(){' .
				'var textarea = document.getElementById("texta' . $j . '");' .
				'textarea.value = editor' .$j. '.getSession().getValue();' .
			'});' .
			'function modefunc' . $j . '() {' . "\n" .
			'    var modelist = ace.require(\'ace/ext/modelist\');' . "\n" .
			'    var filePath = "' . $filename . '";' . "\n" .
			'    var mode = modelist.getModeForPath(filePath).mode;' . "\n" .
			'    editor' .$j. '.getSession().setMode(mode);' . "\n" .
			'    editor' .$j. '.setReadOnly(false);' . "\n" .
			'};' . ' modefunc' . $j . '();' . "\n" .
			'</script>';
	} else {
		$html .= '<div class="tabbertab">' .
			'<h2 class="filename"><a id="source' . $j . '"></a>' .
			htmlspecialchars($filename) . "</h2> <a " .
			"href=\"show_executable.php?id=" . urlencode($id) .
			"&amp;fetch=" . $j . "\">" .
			"<img class=\"picto\" src=\"../images/b_save.png\" alt=\"download\" title=\"download\" /></a> " .
			"<a href=\"show_executable.php?edit_source=1&id=" . urlencode($id) .
			"&amp;rank=" . $j . "\">" .
			"<img class=\"picto\" src=\"../images/edit.png\" alt=\"edit\" title=\"edit\" />" .
			"</a>\n\n";

		$html .= '<pre class="editor" id="editor' . $j . '">'
			. htmlspecialchars($content) . '</pre>' .
			'<script>' .
			'var editor = ace.edit("editor' . $j . '");' .
			'editor.setTheme("ace/theme/eclipse");' .
			'editor.setOptions({ maxLines: Infinity });' .
			'editor.setReadOnly(true);' . "\n" .
			'function modefunc' . $j . '() {' . "\n" .
			'    var modelist = ace.require(\'ace/ext/modelist\');' . "\n" .
			'    var filePath = "' . $filename . '";' . "\n" .
			'    var mode = modelist.getModeForPath(filePath).mode;' . "\n" .
			'    editor.getSession().setMode(mode);' . "\n" .
			'};' . ' modefunc' . $j . '();' . "\n" .
			'</script>';
	}

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

if ( isset($_GET['edit_source']) ) {
	echo addHidden('storeid', $id);
	echo addSubmit('save');

	echo addEndForm();
}

require(LIBWWWDIR . '/footer.php');
