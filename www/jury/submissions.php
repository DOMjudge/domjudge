<?php
/**
 * View the submissionqueue
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$show = 0;
if ( isset($_REQUEST['show']) ) {
	$show = (int)$_REQUEST['show'];
	if($show < 0 || $show > 3)	$show = 0;
}
if ( isset($_REQUEST['view']) ) {
	switch( $_REQUEST['view'] ) {
		case 'unverified':	$show = 2;	break;
		case 'all':			$show = 1;	break;
		case 'newest':
		default:			$show = 0;	break;
	}
}

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/submissions.php?show=' . $show;
$title = 'Submissions';

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( $show == 2 ) $restrictions['verified'] = 0;

require_once(SYSTEM_ROOT . '/lib/www/forms.php');

echo addForm('submissions.php', 'get')
	. "<p>\n"
	. addSubmit('newest',     'view', null, ($show != 0))
	. addSubmit('all',        'view', null, ($show != 1))
	. addSubmit('unverified', 'view', null, ($show != 2))
	. "</p>\n"
	. addEndForm();

putSubmissions($cdata, $restrictions, TRUE, ($show == 0 ? 50 : 0));

require(SYSTEM_ROOT . '/lib/www/footer.php');
