<?php
/**
 * Clarification Request Management
 *
 * $Id: teams.php 303 2004-06-14 09:55:36Z nkp0405 $
 */

require('init.php');
$title = 'Clarification Request';
require('../header.php');
require('menu.php');

$id = (int)$_REQUEST['id'];
if(!$id)	error ("Missing clarification id");


echo "<h1>Clarification Request</h1>\n\n";

$reqdata = putRequest($id, $login);

$list = $DB->q("SELECT r.respid
	FROM clar_response r
	WHERE r.reqid = $id AND ( r.rcpt = NULL OR r.rcpt = %s)
	ORDER BY r.submittime DESC", $login);

echo "<h3>Clarification Response:</h3>\n\n";
if ( $list->count() == 0 ) {
	echo "No jury response yet.\n\n";
} else {
	while ( $row = $list->next())
	{
		putResponse($row['respid'], false, false);
	}
}

require('../footer.php');
