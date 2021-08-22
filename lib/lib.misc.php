<?php declare(strict_types=1);
/**
 * Miscellaneous helper functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('lib.wrappers.php');

/**
 * Calculate timelimit overshoot from actual timelimit and configured
 * overshoot that can be specified as a sum,max,min of absolute and
 * relative times. Returns overshoot seconds as a float.
 */
function overshoot_time(float $timelimit, string $overshoot_cfg) : float
{
    $tokens = preg_split('/([+&|])/', $overshoot_cfg, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($tokens)!=1 && count($tokens)!=3) {
        error("invalid timelimit overshoot string '$overshoot_cfg'");
    }

    $val1 = overshoot_parse($timelimit, $tokens[0]);
    if (count($tokens)==1) {
        return $val1;
    }

    $val2 = overshoot_parse($timelimit, $tokens[2]);
    switch ($tokens[1]) {
    case '+': return $val1 + $val2;
    case '|': return max($val1, $val2);
    case '&': return min($val1, $val2);
    }
    error("invalid timelimit overshoot string '$overshoot_cfg'");
}

/**
 * Helper function for overshoot_time(), returns overshoot for single token.
 */
function overshoot_parse(float $timelimit, string $token) : float
{
    $res = sscanf($token, '%d%c%n');
    if (count($res)!=3) {
        error("invalid timelimit overshoot token '$token'");
    }
    list($val, $type, $len) = $res;
    if (strlen($token)!=$len) {
        error("invalid timelimit overshoot token '$token'");
    }

    if ($val<0) {
        error("timelimit overshoot cannot be negative: '$token'");
    }
    switch ($type) {
    case 's': return $val;
    case '%': return $timelimit * 0.01*$val;
    default: error("invalid timelimit overshoot token '$token'");
    }
}

/**
 * Call alert plugin program to perform user configurable action on
 * important system events. See default alert script for more details.
 */
function alert(string $msgtype, string $description = '')
{
    system(LIBDIR . "/alert '$msgtype' '$description' &");
}

/**
 * Functions to support graceful shutdown of daemons upon receiving a signal
 */
function sig_handler(int $signal, $siginfo = null)
{
    global $exitsignalled, $gracefulexitsignalled;

    logmsg(LOG_DEBUG, "Signal $signal received");

    switch ($signal) {
        case SIGHUP:
            $gracefulexitsignalled = true;
            // no break
        case SIGINT:   # Ctrl+C
        case SIGTERM:
            $exitsignalled = true;
    }
}

function initsignals()
{
    global $exitsignalled;

    $exitsignalled = false;

    if (! function_exists('pcntl_signal')) {
        logmsg(LOG_INFO, "Signal handling not available");
        return;
    }

    logmsg(LOG_DEBUG, "Installing signal handlers");

    // Install signal handler for TERMINATE, HANGUP and INTERRUPT
    // signals. The sleep() call will automatically return on
    // receiving a signal.
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
}

/**
 * Forks and detaches the current process to run as a daemon. Similar
 * to the daemon() call present in Linux and *BSD.
 *
 * Argument pidfile is an optional filename to check for running
 * instances and write PID to.
 *
 * Either returns successfully or exits with an error.
 */
function daemonize($pidfile = null)
{
    switch ($pid = pcntl_fork()) {
        case -1: error("cannot fork daemon");
        case  0: break; // child process: do nothing here.
        default: exit;  // parent process: exit.
    }

    if (($pid = posix_getpid())===false) {
        error("failed to obtain PID");
    }

    // Check and write PID to file
    if (!empty($pidfile)) {
        if (($fd=@fopen($pidfile, 'x+'))===false) {
            error("cannot create pidfile '$pidfile'");
        }
        $str = "$pid\n";
        if (@fwrite($fd, $str)!=strlen($str)) {
            error("failed writing PID to file");
        }
        register_shutdown_function('unlink', $pidfile);
    }

    // Notify user with daemon PID before detaching from TTY.
    logmsg(LOG_NOTICE, "daemonizing with PID = $pid");

    // Close std{in,out,err} file descriptors
    if (!fclose(STDIN) || !($GLOBALS['STDIN']  = fopen('/dev/null', 'r')) ||
        !fclose(STDOUT) || !($GLOBALS['STDOUT'] = fopen('/dev/null', 'w')) ||
        !fclose(STDERR) || !($GLOBALS['STDERR'] = fopen('/dev/null', 'w'))) {
        error("cannot reopen stdio files to /dev/null");
    }

    // FIXME: We should really close all other open file descriptors
    // here, but PHP does not support this.

    // Start own process group, detached from any tty
    if (posix_setsid()<0) {
        error("cannot set daemon process group");
    }
}

/**
 * Output generic version information and exit.
 */
function version() : string
{
    echo SCRIPT_ID . " -- part of DOMjudge version " . DOMJUDGE_VERSION . "\n" .
        "Written by the DOMjudge developers\n\n" .
        "DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n" .
        "are welcome to redistribute it under certain conditions.  See the GNU\n" .
        "General Public Licence for details.\n";
    exit(0);
}
