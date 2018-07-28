<?php
require_once('../configure.php');
require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/auth.php');

// Where the user was trying to go, we'll redirect them back
if (session_id() == "") {
    session_start();
}
$next = $_SESSION['redirect_after_login'];


if (isset($_REQUEST['code'])) {
    try {
        do_login_oidc();
    } catch (OpenIDConnectClientException $exception) {
        // Retry the login request if it was something like
        if ($_REQUEST['code']) {
            header("Location: $next");
            exit;
        } else {
            throw $exception;
        }
    }
} else {
    header("Location: ../");
    exit;
}


// Redirect to wherever the user was trying to go initially
header("Location: $next");
exit;
