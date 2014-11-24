<?php

if ( empty($_SERVER['HTTP_REFERER']) ) die("Missing referrer header.");

setcookie('domjudge_cid', $_REQUEST['cid']);

header('Location: ' . $_SERVER['HTTP_REFERER']);
