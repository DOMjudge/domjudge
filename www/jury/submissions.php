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

$title = 'Submissions' . ( $showverified ? '' : ' (only unverified)' );

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( !$showverified ) $restrictions['verified'] = 0;

require_once(SYSTEM_ROOT . '/lib/www/forms.php');

echo addForm('submissions.php', 'get') . "<p>\n" .
	addHidden('showverified', (int)!$showverified) .
	addSubmit(($showverified ? 'show only unverified' : 'show all')) . "</p>\n" .
	addEndForm();

putSubmissions($cdata, $restrictions, TRUE);

require(SYSTEM_ROOT . '/lib/www/footer.php');
