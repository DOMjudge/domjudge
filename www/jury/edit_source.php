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

	submit_solution($_POST['submitter'], $_POST['probid'], $_POST['langid'], $tmpfname);
	unlink($tmpfname);

	header('Location: submissions.php');
	exit;
}

$id = (int)$_GET['id'];
$source = $DB->q('MAYBETUPLE SELECT * FROM submission
                  LEFT JOIN language USING(langid)
                  WHERE submitid = %i',$id);

if ( empty($source) ) error ("Submission $id not found");

$sourcefile = getSourceFilename($source['cid'],$id,$source['teamid'],
                                $source['probid'],$source['langid']);

$title = 'Source: ' . htmlspecialchars($sourcefile);
require(LIBWWWDIR . '/header.php');

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
echo addHidden('submitter', 'domjudge');
echo addSubmit('submit');

echo addEndForm();

require(LIBWWWDIR . '/footer.php');
