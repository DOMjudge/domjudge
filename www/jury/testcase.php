<?php
/**
 * View/edit testcase
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$probid = @$_REQUEST['probid'];

require('init.php');


$INOROUT = array('input','output');

// Download testcase
if ( isset ($_GET['fetch']) && in_array($_GET['fetch'], $INOROUT)) {
	$fetch = $_GET['fetch'];
	$filename = $probid . "." . $fetch;

	$size = $DB->q("MAYBEVALUE SELECT OCTET_LENGTH($fetch)
		FROM testcase WHERE probid = %s", $probid);

	// sanity check before we start to output headers
	if ( empty($size) || !is_numeric($size)) error ("Problem while fetching testcase");

	header("Content-Type: application/octet-stream; name=\"$filename\"");
	header("Content-Disposition: inline; filename=\"$filename\"");
	header("Content-Length: $size");

	// This may not be good enough for large testsets, but streaming them
	// directly from the database query result seems overkill to implement.
	echo $DB->q("VALUE SELECT $fetch FROM testcase WHERE probid = %s", $probid);

	exit(0);
}

$title = 'Testcase for problem '.htmlspecialchars(@$probid);

require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

requireAdmin();

if ( ! $probid ) error("Missing or invalid problem id");

echo "<h1>" . $title ."</h1>\n\n";

$result = '';
if ( isset($_POST['probid']) ) {
	foreach($INOROUT as $inout) {

		if ( !empty($_FILES['update_'.$inout]['name']) ) {

			// Check for upload errors:
			checkFileUpload ( $_FILES['update_'.$inout]['error'] );

			$content = file_get_contents($_FILES['update_'.$inout]['tmp_name']);
			if ( $DB->q("VALUE SELECT count(testcaseid)
 			             FROM testcase WHERE probid = %s", $probid) ) {
				$DB->q("UPDATE testcase SET md5sum_$inout = %s, $inout = %s
				        WHERE probid = %s",
				       md5($content), $content, $probid);
			} else {
				$DB->q("INSERT INTO testcase (probid,md5sum_$inout,$inout)
				        VALUES (%s,%s,%s)",
				       $probid, md5($content), $content);
			}
			$result .= "<li> Updated $inout from " .
				htmlspecialchars($_FILES['update_'.$inout]['name']) .
				" (" . htmlspecialchars($_FILES['update_'.$inout]['size']) .
				" B)</li>\n";
		}
	}

}
if ( !empty($result) ) echo "<ul>\n$result</ul>\n\n";


$data = $DB->q('MAYBETUPLE SELECT 
	OCTET_LENGTH(input) AS size_input, md5sum_input,
	OCTET_LENGTH(output) AS size_output, md5sum_output
	FROM testcase WHERE probid = %s', $probid);

echo addForm('', 'post', null, 'multipart/form-data') . 
	addHidden('probid', $probid);

foreach($INOROUT as $inout) {

	echo "<h2>" . ucfirst($inout) . "</h2>\n\n";
	if ( isset($data['size_'.$inout]) ) {
		echo "<table>\n<tr><td>Size:</td><td>" . htmlspecialchars($data['size_' . $inout]) . " bytes</td></tr>\n";
		echo "<tr><td>MD5 sum:</td><td>" . htmlspecialchars($data['md5sum_' . $inout]) . "</td></tr>\n";
		echo "<tr><td>Download:</td><td class=\"filename\"><a href=\"./testcase.php?probid=" .
			urlencode($probid) . "&amp;fetch=" . $inout . "\">" .
			htmlspecialchars($probid) . "." . $inout . "</a></td>\n";
		echo "<tr><td>Replace with:</td><td>" . addFileField('update_'.$inout) . "</td></tr>\n";

		echo "</table>\n";
	} else {
		echo "<p>No testcase yet.</p>\n";
		echo "<p>Create new: " . addFileField('update_'.$inout) . "</p>";
	}
}

echo "<p>" . addSubmit('Change testcases') . "</p>\n";
	addEndForm() ;

echo "<p><a href=\"problem.php?id=" . urlencode($probid) . ">back to problem " .
	htmlspecialchars($probid) . "</a></p>\n\n";

require(LIBWWWDIR . '/footer.php');
