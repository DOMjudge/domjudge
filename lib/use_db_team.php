<?php
/* $Id$ */

require('lib.database.php');

// create new db object with login data
$DB = new db ($DBDATA['team']['db'], $DBDATA['team']['host'], $DBDATA['team']['user'], $DBDATA['team']['pass']);

// don't need this anymore
unset ($DBDATA);
