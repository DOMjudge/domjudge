<?php
/**
 * Handle web submissions
 *
 * $Id$
 */

require('init.php');

if ( ! ENABLEWEBSUBMIT ) {
	error("Websubmit disabled!");
}

if ( !isset($_POST['submit']) ) {
	header('Location: websubmit.php');
	return;
}

ob_implicit_flush();

// helper to output an error message.
function err($string) {
	echo "<font color=\"#FF0000\"><b><u>ERROR</u>: " .
		htmlspecialchars($string) . "</b></font><br />\n";
	require('../footer.php');
	exit;
}

$title = 'Websubmit';
require('../header.php');
require('menu.php');

$waitsubmit = 5;

echo "<h2>Websubmit - upload status</h2>\n\n";

switch ( $_FILES['code']['error'] ) {
	case 1:
		error('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
	case 2:
		error('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.');
	case 3:
		error('The uploaded file was only partially uploaded.');
	case 4:
		warning('No file was uploaded.');
		break;
	case 6:
		error('Missing a temporary folder.');
	case 7:
		error('Failed to write file to disk.');
}

$filename = $_FILES['code']['name'];

/*	Determine the problem */
$probid = @$_REQUEST['probid'];

if ( empty($probid) ) {
	if ( strpos($filename, '.') === false ) {
		err('Unable to autodetect the problem from the uploaded filename');
	}
	$probid = strtolower(substr($filename, 0, strpos($filename, '.')));
}

$prob = $DB->q('MAYBETUPLE SELECT probid, name FROM problem
                WHERE allow_submit = 1 AND probid = %s AND cid = %i',
               $probid, getCurContest());

if ( ! isset($prob) ) err("Unable to find problem '$probid'");

/*	Determine the language */
$langext = @$_REQUEST['langext'];

if ( empty($langext) ) {
	if ( strrpos($filename, '.') === false ) {
		err('Unable to autodetect the language from the uploaded filename');
	}
	$fileext = strtolower(substr($filename, strrpos($filename, '.')+1));

	$all_lang_exts = explode(" ", LANG_EXTS);
	
	foreach ($all_lang_exts as $langextlist) {
		$langexts = explode(",", $langextlist);

		// Skip first element: that's the language name
		for ($i = 1; $i < count($langexts); $i++) {
			if ( $langexts[$i]==$fileext ) $langext = $langexts[1];
		}
	}
	
	if ( empty($langext) ) err("Unable to find language for extension '$fileext'");
}

$lang = $DB->q('MAYBETUPLE SELECT langid, name FROM language
                WHERE extension = %s AND allow_submit = 1', $langext);

if ( ! isset($lang) ) err("Unable to find language '$langext'");

echo "<table>\n" .
	"<tr><td>Problem: </td><td><i>".$prob['name']."</i></td></tr>\n" .
	"<tr><td>Language:</td><td><i>".$lang['name']."</i></td></tr>\n" .
	"</table>\n";

$ipstr = str_replace(".","-",$ip);

$tmpfile = $_FILES['code']['tmp_name'];

$desttemp = INCOMINGDIR . "/websubmit.$probid.$login.$ipstr.XXXXXX.$langext";
$destfile = mkstemps($desttemp,strlen($langext)+1);
if ( $destfile === FALSE ) {
	error("Failed to create file from template '". basename($desttemp) . "'");
}

if ( ! move_uploaded_file($tmpfile, $destfile) ) {
	error("Failed to move uploaded file '$tmpfile' to '$destfile'");
}

for($i=0; $i<$waitsubmit; $i++) {
	sleep(1);
	if ( ! file_exists($destfile) ) break;
}

if ( file_exists($destfile) ) {
	echo "<p>Upload not (yet) successful.</p>\n";
} else if ( file_exists(INCOMINGDIR . "/rejected-" . basename($destfile)) ) {
	echo "<p>Upload failed.</p>\n";
} else {
	echo "<p>Upload successful.</p>\n";
}

require('../footer.php');
