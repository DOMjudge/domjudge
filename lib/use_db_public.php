<?php
/* $Id$ */

require('lib.database.php');
if ( !defined('DBNAME') || !defined('DBSERVER')  || empty($DBLOGIN)) die ("DBNAME, DBSERVER or \$DBLOGIN not defined.");

// create new db object with login data
$DB = new db (DBNAME, DBSERVER, $DBLOGIN['public']['user'], $DBLOGIN['public']['pass']);

// don't need this anymore
unset ($DBLOGIN);
