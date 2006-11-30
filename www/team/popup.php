<?php
/**
 * This is the popup which informs about new clarifications.
 *
 * $Id$
 */

require('init.php');
$popup = false;
$title = 'Attention!';
include('../header.php');

foreach($_REQUEST['new'] as $value)
{
	switch($value) {
	case 'CLARIFICATION':
		echo "<h1>You have received a new clarification!</h1>\n";
		break;
	case 'SUBMISSION':
		echo "<h1>The result of your submission is now available!</h1>\n";
		break;
	default:
	}
}

include('../footer.php');
