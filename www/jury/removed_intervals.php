<?php
/**
 * Add and delete removed contest time intervals.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

require(LIBWWWDIR . '/checkers.jury.php');

requireAdmin();

$cmd = $_REQUEST['cmd'];
$mycid = (int)$_REQUEST['cid'];

// Compare intervals by starttime for sorting
function cmpintv($a, $b)
{
	return difftime($a['starttime'], $b['starttime']);
}

// Check if combined contest and removed intervals data is valid,
// contest data may get updated.
function checkall($newintv = null)
{
	global $cmd, $mycid, $contest, $removals, $CHECKER_ERRORS;

	$CHECKER_ERRORS = array();

	$contest = check_contest($contest, array('cid' => $mycid), $removals);

	if ( count($CHECKER_ERRORS) ) {
		error("Error trying to $cmd removed_interval:\n" .
		      implode(";\n", $CHECKER_ERRORS));
	}
}

$contest = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $mycid);

switch ( $cmd ) {
case 'add':
	$intv = array('cid' => $mycid,
	              'starttime' => $_REQUEST['starttime'],
	              'endtime'   => $_REQUEST['endtime']);
	$removals = $DB->q('TABLE SELECT * FROM removed_interval WHERE cid = %i', $mycid);
	$removals[] = $intv;
	usort($removals, 'cmpintv');

	checkall($intv);

	$DB->q('START TRANSACTION');
	$DB->q('INSERT INTO removed_interval SET %S', $intv);
	$DB->q('UPDATE contest SET %S WHERE cid = %i', $contest, $mycid);
	$DB->q('COMMIT');
	break;

case 'delete':
	$removals = $DB->q('TABLE SELECT * FROM removed_interval
	                    WHERE cid = %i AND intervalid != %i',
	                   $mycid, $_REQUEST['intervalid']);

	checkall();

	$DB->q('START TRANSACTION');
	$DB->q('DELETE FROM removed_interval WHERE intervalid = %i', $_REQUEST['intervalid']);
	$DB->q('UPDATE contest SET %S WHERE cid = %i', $contest, $mycid);
	$DB->q('COMMIT');
	break;

default:
	error("Unknown cmd.");
}

header('Location: contest.php?id=' . $mycid);
