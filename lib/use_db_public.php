<?php
/* $Id$ */

require('lib.database.php');

// create new db object with login data
$DB = new db (DBNAME, DBSERVER, $DBLOGIN['public']['user'], $DBLOGIN['public']['pass']);

// don't need this anymore
unset ($DBDATA);
