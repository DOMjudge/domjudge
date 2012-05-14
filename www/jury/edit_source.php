<?php
/**
 * Edit source code and resubmit to the database.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

// submit code
if ( isset($_POST['submitter']) ) {
	$sources = $DB->q('TABLE SELECT *
			   FROM submission_file LEFT JOIN submission USING(submitid)
			   WHERE submitid = %i ORDER BY rank', $_POST['origsubmitid']);

	$files = array();
	$filenames = array();
	foreach($sources as $sourcedata)
	{
		if ( !($tmpfname = mkstemps(TMPDIR."/edit_source-XXXXXX",0)) ) {
			error("Could not create temporary file.");
		}
		file_put_contents($tmpfname, $_POST['source' . $sourcedata['rank']]);

		$files[] = $tmpfname;
		$filenames[] = $sourcedata['filename'];
	}

	submit_solution($_POST['submitter'], $_POST['probid'], $_POST['langid'],
	                $files, $filenames, $_POST['origsubmitid']);

	foreach($files as $file)
	{
		unlink($file);
	}

	header('Location: submissions.php');
	exit;
}

$id = (int)$_GET['id'];
$submission = $DB->q('MAYBETUPLE SELECT * FROM submission s
                  WHERE submitid = %i', $id);

if ( empty($submission) ) error ("Submission $id not found");

$title = 'Edit Source: s' . $id;
require(LIBWWWDIR . '/header.php');


echo '<h2><a name="source"></a>Edit submission ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source files</h2>\n\n";

echo addForm('edit_source.php', 'post', null, 'multipart/form-data');


$sources = $DB->q('TABLE SELECT *
                   FROM submission_file LEFT JOIN submission USING(submitid)
                   WHERE submitid = %i ORDER BY rank', $id);

echo '<script type="text/javascript" src="../js/tabber.js"></script>' .
	'<div class="tabber">';
foreach($sources as $sourcedata)
{
	echo '<div class="tabbertab' . ($_GET['rank'] === $sourcedata['rank'] ? ' tabbertabdefault' : '') .'">';
	echo '<h2 class="filename">' . htmlspecialchars($sourcedata['filename']) . '</h2>';
	echo addTextArea('source' . $sourcedata['rank'], $sourcedata['sourcecode'], 120, 40) . "<br />\n";
	echo "</div>\n";
}
echo "</div>\n";

$probs = $DB->q('KEYVALUETABLE SELECT probid, name FROM problem WHERE
                 allow_submit = 1 AND cid = %i ORDER BY name', $cid);
$langs = $DB->q('KEYVALUETABLE SELECT langid, name FROM language WHERE
                 allow_submit = 1 ORDER BY name');

echo addSelect('probid', $probs, $submission['probid'], true);
echo addSelect('langid', $langs, $submission['langid'], true);

echo addHidden('teamid', $submission['teamid']);
echo addHidden('submitter', 'domjudge');
echo addHidden('origsubmitid', $submission['origsubmitid'] === NULL ? $id : $submission['origsubmitid']);
echo addSubmit('submit');

echo addEndForm();

require(LIBWWWDIR . '/footer.php');
