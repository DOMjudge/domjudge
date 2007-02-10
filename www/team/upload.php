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

if ( !isset($_REQUEST['submit']) ) {
	header('Location: websubmit.php');
	return;
}


// helper to output an error message.
function err($string) {
	echo '<div id="uploadstatus" class="error"><u>ERROR</u>: ' .
		htmlspecialchars($string) . "</div>\n";
	
	require('../footer.php');
	exit;
}

ini_set("upload_max_filesize", SOURCESIZE * 1024);

$title = 'Submit';
require('../header.php');

$waitsubmit = 5;

echo "<h2>Submit - upload status</h2>\n\n";

ob_implicit_flush();

switch ( $_FILES['code']['error'] ) {
	case UPLOAD_ERR_INI_SIZE:
		error('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
	case UPLOAD_ERR_FORM_SIZE:
		error('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.');
	case UPLOAD_ERR_PARTIAL:
		error('The uploaded file was only partially uploaded.');
	case UPLOAD_ERR_NO_FILE:
		warning('No file was uploaded.');
		break;
	case 6:	// UPLOAD_ERR_NO_TMP_DIR, constant doesn't exist in our minimal PHP version
		error('Missing a temporary folder.');
	case 7: // UPLOAD_ERR_CANT_WRITE
		error('Failed to write file to disk.');
}

$filename = $_FILES['code']['name'];

/* Determine the problem */
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

/* Determine the language */
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
	"<tr><td>Problem: </td><td><i>".htmlentities($prob['name'])."</i></td></tr>\n" .
	"<tr><td>Language:</td><td><i>".htmlentities($lang['name'])."</i></td></tr>\n" .
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
chmod($destfile, 0644);

for($i=0; $i<$waitsubmit; $i++) {
	sleep(1);
	if ( ! file_exists($destfile) ) break;
}

/* Print everything between the <div> tags on one line for
   easier parsing by commandline submit to webinterface */
echo '<div id="uploadstatus">';
if ( file_exists($destfile) ) {
	echo "<p>Upload not (yet) successful.</p>";
} else if ( file_exists(INCOMINGDIR . "/rejected-" . basename($destfile)) ) {
	echo "<p>Upload failed.</p>";
} else {
	echo "<p>Upload successful.</p>";
}
echo "</div>\n";

require('../footer.php');
