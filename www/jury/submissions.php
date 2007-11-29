<?php
/**
 * View the submissionqueue
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

if ( isset($_REQUEST['showverified']) ) {
	$showverified = $_REQUEST['showverified'] ? 1 : 0;
} else {
	$showverified = 1;
}

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php?showverified=' .
	$showverified;
$title = 'Submissions';

require('../header.php');

echo "<h1>Submissions</h1>\n\n";

$restrictions = array();
if ( !$showverified ) $restrictions['verified'] = 0;

require_once('../forms.php');

echo addForm('submissions.php') . "<p>\n" .
	addHidden('showverified', !$showverified) .
	addSubmit(($showverified ? 'show only non-verified' : 'show all')) . "</p>\n" .
	addEndForm();

putSubmissions($cdata, $restrictions, TRUE);

require('../footer.php');
