<?php

/**
 * Display a clarification response
 *
 * $Id$
 */

$respid = (int)@$_GET['id'];

require('init.php');
$refresh = '30;url=clarification.php?id='.$respid;
$title = 'Clarification Response';
include('../header.php');
include('menu.php');

echo "<h1>Clarification Response</h1>\n\n";

putResponse($respid, false, false);

include('../footer.php');
