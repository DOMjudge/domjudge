<?php

/**
 * Switch a user to the right site based on IP (from database)
 * $Id$
 */

require_once('../etc/config.php');
require_once('../lib/lib.error.php');
require_once('../lib/use_db_public.php');

$ip = $_SERVER['REMOTE_ADDR'];
$res = $DB->q('SELECT ipaddress FROM team WHERE ipaddress = %s', $ip);
if($res->count() > 0) {
	$target = 'team/';
} else {
	$target = 'public/';
}

header('HTTP/1.1 302 Please see this page');
header('Location: http://'.WEBSERVER.'/'.$target);
