<?php

if ( empty($_SERVER['HTTP_REFERER']) ) die("Missing referrer header.");

setcookie('domjudge_notify', $_REQUEST['enable']);

header('Location: ' . $_SERVER['HTTP_REFERER']);
