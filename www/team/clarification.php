<?php

/**
 * Display a clarification response
 *
 * $Id$
 */

$respid = (int)@$_GET['id'];

require('init.php');
$refresh = '30;url=' . getBaseURI() . 'team/clarification.php?id=' . urlencode($respid);
$title = 'Clarification Response';
include('../header.php');
include('menu.php');

echo "<h1>Clarification Response</h1>\n\n";

putResponse($respid, false, false);

include('../footer.php');
