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
	if ( !($tmpfname = mkstemps(TMPDIR."/edit_source-XXXXXX",0)) ) {
		error("Could not create temporary file.");
	}

	file_put_contents($tmpfname, $_POST['source']);

	submit_solution($_POST['submitter'], $_POST['probid'], $_POST['langid'],
	                array($tmpfname), array($_POST['filename']), $_POST['origsubmitid']);
	unlink($tmpfname);

	header('Location: submissions.php');
	exit;
}

$id = (int)$_GET['id'];
$source = $DB->q('MAYBETUPLE SELECT s.*, f.*, l.*, COUNT(g.rank) AS nfiles
                  FROM submission s
                  LEFT JOIN submission_file f ON(s.submitid=f.submitid AND f.rank=0)
                  LEFT JOIN submission_file g ON(s.submitid=g.submitid)
                  LEFT JOIN language l USING(langid)
                  WHERE s.submitid = %i GROUP BY g.submitid',$id);

if ( empty($source) ) error ("Submission $id not found");

$sourcefile = getSourceFilename($source);

$title = 'Source: ' . htmlspecialchars($sourcefile);
require(LIBWWWDIR . '/header.php');

if ( $source['nfiles']>1 ) {
	warning("Submission $id has multiple source files, editing not (yet) supported.");

	require(LIBWWWDIR . '/footer.php');
	return;
}

echo '<h2 class="filename"><a name="source"></a>Submission ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source: " .
	htmlspecialchars($sourcefile) . "</h2>\n\n";

echo addForm('edit_source.php', 'post', null, 'multipart/form-data');
echo addTextArea('source', $source['sourcecode'], 120, 40) . "<br />\n";

$probs = $DB->q('KEYVALUETABLE SELECT probid, name FROM problem WHERE
                 allow_submit = 1 AND cid = %i ORDER BY name', $cid);
$langs = $DB->q('KEYVALUETABLE SELECT langid, name FROM language WHERE
                 allow_submit = 1 ORDER BY name');

echo addSelect('probid', $probs, $source['probid'], true);
echo addSelect('langid', $langs, $source['langid'], true);

echo addHidden('teamid', $source['teamid']);
echo addHidden('filename', $source['filename']);
echo addHidden('submitter', 'domjudge');
echo addHidden('origsubmitid', $source['origsubmitid'] === NULL ? $id : $source['origsubmitid']);
echo addSubmit('submit');

echo addEndForm();

require(LIBWWWDIR . '/footer.php');
