<?php
/**
 * Handle web submissions
 *
 * $Id$
 */

require('init.php');

if ( !isset($_POST['submit']) ) {
	header('location: submit.php');
	return;
}

$title = 'Websubmit';
require('../header.php');
require('menu.php');

echo "<h2>Websubmit - upload status</h2>\n\n";

switch ( $_FILES['code']['error'] ) {
	case 1:
		error('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
	case 2:
		error('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
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
	if( strpos($filename, '.') === false ) {
		error('Unable to autoselect the problem from the uploaded filename');
	}
	$probid = substr($filename, 0, strpos($filename, '.'));
}

$prob = $DB->q('MAYBETUPLE SELECT probid, name FROM problem
                WHERE cid = %d AND probid = %s AND allow_submit = 1',
                getCurContest(), $probid);

if( ! $prob ) error("Unable to find problem '$probid'");

/*	Determine the language */
$langext = @$_REQUEST['langext'];

if ( empty($langext) ) {
	if ( strrpos($filename, '.') === false ) {
		error('Unable to autoselect the language from the uploaded filename');
	}
	$langext = substr($filename, strrpos($filename, '.')+1);
}

$langexts = explode(" ", LANG_EXTS);

$lang = $DB->q('MAYBETUPLE SELECT langid, name FROM language
                WHERE extension = %s AND allow_submit = 1', $langext);

if( ! $lang ) error("Unable to find language '$langext'");

?>
<p>
problem:  <i> <?=$prob['name']?> </i><br/>
language: <i> <?=$lang['name']?> </i><br/>
</p>

<hr/>
<pre>
file is now uploaded to '<?=$_FILES['code']['tmp_name']?>'
and should be moved to a incoming directory
after which submission should also be added to the db.
</pre>
<?
#	if(!move_uploaded_file($_FILES['code']['tmp_name'], TODO $dest)) {
#		error('Failed to move uploaded file.');
#	}

require('../footer.php');
