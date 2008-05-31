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
if ( isset($_REQUEST['showall']) ) {
	$showall = $_REQUEST['showall'] ? 1 : 0;
} else {
	$showall = 0;
}

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php?showverified=' .
	$showverified .  '&showall=' . $showall;

$title = 'Submissions' . ( $showverified ? '' : ' (only unverified)' );

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( !$showverified ) $restrictions['verified'] = 0;

require_once(SYSTEM_ROOT . '/lib/www/forms.php');

echo "<p>\n";
if(!$showall) {
	echo addForm('submissions.php', 'get') .
		 addHidden('showverified', (int)!$showverified) .
		 addHidden('showall', (int)$showall) .
		 addSubmit(($showverified ? 'show only unverified' : 'back')) .
		 addEndForm();
}
if($showverified) {
	echo addForm('submissions.php', 'get') .
		 addHidden('showall', (int)!$showall) .
		 addSubmit((!$showall ? 'show all submissions' : 'back')) .
		 addEndForm();
}
echo "</p>\n" ;

putSubmissions($cdata, $restrictions, TRUE, ($showall ? 0 : 50));

require(SYSTEM_ROOT . '/lib/www/footer.php');
