<?php
/**
 * Edit and save files of an executable zip file.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

// store files FIXME
if ( isset($_POST['storeid']) ) {
	$id = $_POST['storeid'];
	$executable = $DB->q('MAYBETUPLE SELECT * FROM executable
		WHERE execid = %s', $id);
	if ( empty($executable) ) error ("Executable $id not found");
	if ( !($tmpfname = mkstemps(TMPDIR."/executable-XXXXXX",0)) ) {
		error("failed to create temporary file");
	}
	if ( FALSE === file_put_contents($tmpfname, $executable['zipfile']) ) {
		error("failed to write zip file to temporary file");
	}
	$zip = openZipFile($tmpfname);
	$newzip = new ZipArchive;
	if ( !($tmpfname2 = mkstemps(TMPDIR."/executable-XXXXXX",0)) ) {
		error("Could not create temporary file.");
	}

	$res = $newzip->open($tmpfname2, ZipArchive::OVERWRITE);
	if ( $res !== TRUE ) {
		error("Could not create temporary zip file.");
	}
	$skip = 0;
	for ($j = 0; $j < $zip->numFiles; $j++) {
		$filename = $zip->getNameIndex($j);
		if ($filename[strlen($filename)-1] == "/") {
			$skip++;
			continue; // skip directory entries
		}
		$content = $zip->getFromIndex($j);
		if (!mb_check_encoding($content, 'ASCII')) {
			$skip++;
			// add binary files from old zip
			$newzip->addFromString($filename, $content);
			continue;
		}
		// FIXME: skip files based on size?

		// overwrite other files
		$index = $j - $skip;
		$newzip->addFromString($filename, $_POST['texta' . $index]);
	}
	$newzip->close();
	$content = file_get_contents($tmpfname2);

	$DB->q('UPDATE executable SET zipfile = %s, md5sum = %s WHERE execid = %s', $content, md5($content), $id);

	unlink($tmpfname);
	unlink($tmpfname2);

	header('Location: executable.php?id=' . $id);
	exit;
}

$id = $_GET['id'];
$executable = $DB->q('MAYBETUPLE SELECT * FROM executable
	      WHERE execid = %s', $id);
if ( empty($executable) ) error ("Executable $id not found");

$title = 'Edit executable content: ' . $id;
require(LIBWWWDIR . '/header.php');


echo '<h2><a id="source"></a>Edit content of executable ' .
	"<a href=\"executable.php?id=$id\">$id</a></h2>\n\n";

echo addForm($pagename, 'post', null, 'multipart/form-data');

$html = '<script type="text/javascript" src="../js/tabber.js"></script>' .
	'<script src="../js/ace/ace.js" type="text/javascript" charset="utf-8"></script>' .
	'<script src="../js/ace/ext-modelist.js" type="text/javascript" charset="utf-8"></script>' .
	'<div class="tabber">';
if ( !($tmpfname = mkstemps(TMPDIR."/executable-XXXXXX",0)) ) {
	error("failed to create temporary file");
}
if ( FALSE === file_put_contents($tmpfname, $executable['zipfile']) ) {
	error("failed to write zip file to temporary file");
}
$zip = openZipFile($tmpfname);
for ($j = 0; $j < $zip->numFiles; $j++) {
	$filename = $zip->getNameIndex($j);
	if ($filename[strlen($filename)-1] == "/") {
		continue; // skip directory entries
	}
        $content = $zip->getFromIndex($j);
	if (!mb_check_encoding($content, 'ASCII')) {
		$skippedBinary[] = $filename;
		continue; // skip binary files
	}
	// FIXME: skip files based on size?
	// FIXME: use a common function to view syntax highlighted files in combination with tabbed view
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

echo addHidden('storeid', $id);
echo addSubmit('submit');

echo addEndForm();

require(LIBWWWDIR . '/footer.php');
