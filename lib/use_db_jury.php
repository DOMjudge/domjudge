<?php
/* $Id$ */

require('lib.database.php');

// create new db object with login data
$DB = new db (DBNAME, DBSERVER, $DBLOGIN['jury']['user'], $DBLOGIN['jury']['pass']);

// don't need this anymore
unset ($DBDATA);
