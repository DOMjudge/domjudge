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

$result = '';
if ( isset($_POST['probid']) ) {
	foreach(array('input','output') as $inout) {
		if ( !empty($_FILES['update_'.$inout]['tmp_name']) ) {
			$content = file_get_contents($_FILES['update_'.$inout]['tmp_name']);
			if ( $DB->q("VALUE SELECT count(id) FROM testcase WHERE probid = %s", $probid) ) {
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

$title = 'Testcase for problem '.htmlspecialchars(@$probid);

require(SYSTEM_ROOT . '/lib/www/header.php');
require(SYSTEM_ROOT . '/lib/www/forms.php');

if ( ! $probid ) error("Missing or invalid problem id");

echo "<h1>" . $title ."</h1>\n\n";

if ( !empty($result) ) echo "<ul>\n$result</ul>\n\n";

$data = $DB->q('MAYBETUPLE SELECT 
	OCTET_LENGTH(input) AS size_input, md5sum_input,
	OCTET_LENGTH(output) AS size_output, md5sum_output
	FROM testcase WHERE probid = %s', $probid);

echo addForm('testcase.php', 'post', null, 'multipart/form-data') . 
	addHidden('probid', $probid);

foreach(array('input','output') as $inout) {

	echo "<h2>" . ucfirst($inout) . "</h2>\n\n";
	echo "<p>Size: " . htmlspecialchars($data['size_' . $inout]) . " bytes<br />\n";
	echo "MD5 sum: " . htmlspecialchars($data['md5sum_' . $inout]) . "<br />\n";

	echo "Replace with: " . addFileField('update_'.$inout) . "</p>";
}

echo addSubmit('Change testcase') .
	addEndForm() ;

echo "<p><a href=\"problem.php?id=" . urlencode($probid) . ">back to problem " .
	htmlspecialchars($probid) . "</a></p>\n\n";

require(SYSTEM_ROOT . '/lib/www/footer.php');
