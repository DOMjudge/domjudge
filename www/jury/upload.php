<?php
/**
 * Upload a new problem in a zip archive
 *
 * $Id: upload.php 3062 2010-01-07 22:43:13Z werth $
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

require(LIBDIR .  '/relations.php');
require(LIBWWWDIR . '/checkers.jury.php');

if ( !empty($_FILES['archive']['name']) ) {
	checkFileUpload( $_FILES['archive']['error'] );
	$zip = openZipFile($_FILES['archive']['tmp_name']);
	$probid = '#' . $DB->q('VALUE SELECT COUNT(*) FROM problem');
	
	$properties = $zip->getFromName("properties.ini");
	if ($properties !== FALSE) {
		$ini_array = parse_ini_string($properties);
		if ($ini_array !== FALSE && isset($ini_array['probid'])) {
			$probid = $ini_array['probid'];
		}
	}
	$DB->q('INSERT INTO problem (probid, name, cid) VALUES (%s,%s,%s)',
		$probid, 'unknown', (int)$cdata['cid']);
	importZippedProblem($probid, $zip);
	$zip->close();
	header('Location: '.getBaseURI().'jury/problems.php');
}


require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/forms.php');

echo "<h1>Upload problem archive</h1>\n\n" .
	addForm('upload.php', 'post', null, 'multipart/form-data') .
	addFileField('archive') .
	addSubmit('Upload') .
	addEndForm();
