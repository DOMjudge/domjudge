<?php
/**
 * View the submissionqueue
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'all');

$view = 0;
if ( isset($_REQUEST['view']) ) {
	// did someone press any of the three view buttons?
	for ($i=0; $i<count($viewtypes); ++$i) {
		if ( isset($_REQUEST['view'][$i]) ) $view = $i;
	}
}

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php?' . 
	urlencode('view[' . $view . ']=' . $viewtypes[$view]);
$title = 'Submissions';

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( $viewtypes[$view] == 'unverified' ) $restrictions['verified'] = 0;

require_once(SYSTEM_ROOT . '/lib/www/forms.php');

echo addForm('submissions.php', 'get') . "<p>\n";
for($i=0; $i<count($viewtypes); ++$i) {
	echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
echo "</p>\n" . addEndForm();

putSubmissions($cdata, $restrictions, TRUE, ($viewtypes[$view] == 'newest' ? 50 : 0));

require(SYSTEM_ROOT . '/lib/www/footer.php');
