<?php
/* $Id$ */

require('lib.database.php');

// create new db object with login data
$DB = new db ($DBDATA['jury']['db'], $DBDATA['jury']['host'], $DBDATA['jury']['user'], $DBDATA['jury']['pass']);

// don't need this anymore
unset ($DBDATA);
