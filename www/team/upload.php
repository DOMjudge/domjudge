<?php
/**
 * Handle web submissions
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Submit';

if ( !isset($_POST['submit']) ) {
	header('Location: ./');
	return;
}
if ( is_null($cid) ) {
	require(LIBWWWDIR . '/header.php');
	echo "<p class=\"nodata\">No active contest</p>\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}
$now = now();
if ( difftime($cdata['starttime'], $now) > 0 ) {
	require(LIBWWWDIR . '/header.php');
	echo "<p class=\"nodata\">Contest has not yet started.</p>\n";
	require(LIBWWWDIR . '/footer.php');
	exit;
}


/** helper to output an error message. */
function err($string)
{
	// Annoying PHP: we need to import global variables here...
	global $title;

	require(LIBWWWDIR . '/header.php');

	echo "<h2>Submit - error</h2>\n\n";

	echo '<div id="uploadstatus">';
	logmsg(LOG_WARNING, $string);
	echo '</div>';

	require(LIBWWWDIR . '/footer.php');
	exit;
}

// rebuild array of filenames, paths to get rid of empty upload fields
$FILEPATHS = $FILENAMES = array();
foreach($_FILES['code']['tmp_name'] as $fileid => $tmpname ) {
	if ( !empty($tmpname) ) {
		checkFileUpload($_FILES['code']['error'][$fileid]);
		$FILEPATHS[] = $_FILES['code']['tmp_name'][$fileid];
		$FILENAMES[] = $_FILES['code']['name'][$fileid];
	}
}

// FIXME: the following checks are also performed inside
// submit_solution.

/* Determine the problem */
$probid = @$_POST['probid'];
$prob = $DB->q('MAYBETUPLE SELECT probid, name FROM problem
                INNER JOIN contestproblem USING (probid)
                WHERE allow_submit = 1 AND probid = %i AND cid = %i',
               $probid, $cid);

if ( ! isset($prob) ) err("Unable to find problem p$probid");
$probid = $prob['probid'];

/* Determine the language */
$langid = @$_POST['langid'];
$lang = $DB->q('MAYBETUPLE SELECT langid, name FROM language
                WHERE langid = %s AND allow_submit = 1', $langid);

if ( ! isset($lang) ) err("Unable to find language '$langid'");
$langid = $lang['langid'];

$sid = submit_solution($teamid, $probid, $cid, $langid, $FILEPATHS, $FILENAMES);

auditlog('submission', $sid, 'added', null, null, $cid);

header('Location: index.php?submitted=' . urlencode($sid) );
