<?php

setcookie('domjudge_refresh', $_REQUEST['enable']);

header('Location: ' . $_SERVER['HTTP_REFERER']);
