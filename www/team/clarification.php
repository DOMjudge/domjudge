<?php

/**
 * Display a clarification response
 *
 * $Id$
 */

require('init.php');
$title = 'Clarification Response';
include('../header.php');
include('menu.php');

$respid = (int)$_GET['id'];

echo "<h1>Clarification Response</h1>\n\n";

putResponse($respid, false, false);

include('../footer.php');
