<?php
/* $Id$ */

require('lib.database.php');

// create new db object with login data
$DB = new db ($DBDATA['public']['db'], $DBDATA['public']['host'], $DBDATA['public']['user'], $DBDATA['public']['pass']);

// don't need this anymore
unset ($DBDATA);
