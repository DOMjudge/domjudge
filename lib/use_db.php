<?php
// deze file moet altijd dicht staan!

require('lib.database.php');
require('lib.handig.php');

$db_host = 'localhost';
$db_user = 'nkp';
$db_db   = 'nkpjury';
$db_pass = 'JudgeJudy';

$DB = new db ($db_db, $db_host, $db_user, $db_pass);
