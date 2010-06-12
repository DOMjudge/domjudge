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

// Restore most recent view from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_submissionview']) && isset($viewtypes[$_COOKIE['domjudge_submissionview']]) ) {
	$view = $_COOKIE['domjudge_submissionview'];
}

if ( isset($_REQUEST['view']) ) {
	// did someone press any of the three view buttons?
	foreach ($viewtypes as $i => $name) {
		if ( isset($_REQUEST['view'][$i]) ) $view = $i;
	}
}

require('init.php');
$refresh = '15;url=submissions.php?' .
	urlencode('view[' . $view . ']') . '=' . urlencode($viewtypes[$view]);
$title = 'Submissions';

// Set cookie of submission view type, expiry defaults to end of session.
if ( version_compare(PHP_VERSION, '5.2') >= 0 ) {
	// HTTPOnly Cookie, while this cookie is not security critical
	// it's a good habit to get into.
	setcookie('domjudge_submissionview', $view, null, null, null, null, true);
} else {
	setcookie('domjudge_submissionview', $view);
}

require(LIBWWWDIR . '/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( $viewtypes[$view] == 'unverified' ) $restrictions['verified'] = 0;

require_once(LIBWWWDIR . '/forms.php');

echo addForm('submissions.php', 'get') . "<p>\n";
for($i=0; $i<count($viewtypes); ++$i) {
	echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
echo "</p>\n" . addEndForm();

putSubmissions($cdata, $restrictions, ($viewtypes[$view] == 'newest' ? 50 : 0));

require(LIBWWWDIR . '/footer.php');
