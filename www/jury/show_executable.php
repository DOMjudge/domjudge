<?php
/**
 * Show files in an executable.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = $_GET['id'];
$executable = $DB->q('MAYBETUPLE SELECT * FROM executable
	      WHERE execid = %s', $id);
if ( empty($executable) ) error ("Executable $id not found");

// Download was requested
if ( isset($_GET['fetch']) ) {
	error("downloading of single files not implemented yet");
}

$title = "Executable: $id";
require(LIBWWWDIR . '/header.php');

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
	$html .= '<div class="tabbertab">' .
		'<h2 class="filename"><a id="source' . $j . '"></a>' .
		htmlspecialchars($filename) . "</h2> <a " .
		"href=\"show_executable.php?id=" . urlencode($id) .
		"&amp;fetch=" . $j . "\">" .
		"<img class=\"picto\" src=\"../images/b_save.png\" alt=\"download\" title=\"download\" /></a> " .
		"<a href=\"edit_executable.php?id=" . urlencode($id) .
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

	$html .= '</div>';
}
$html .= "</div>";

echo "<h2>Executable zip file for " .htmlspecialchars($id). "</h2>";
if ( count($skippedBinary) > 0 ) {
	echo "binary files:\n";
	echo "<ul>";
	foreach ($skippedBinary as $skipped) {
		echo "<li>" . htmlspecialchars($skipped) . "</li>";
	}
	echo "</ul>";
}
echo $html;

require(LIBWWWDIR . '/footer.php');
