<?php
/**
 * Clarification Request Management
 *
 * $Id: teams.php 303 2004-06-14 09:55:36Z nkp0405 $
 */

require('init.php');
$title = 'Clarification Response';
require('../header.php');
require('menu.php');

$id = (int)$_REQUEST['id'];
if(!$id)	error ("Missing clarification id");

echo "<h1>Clarification Response r$id</h1>\n\n";

putResponse($id);

require('../footer.php');
