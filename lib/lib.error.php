<?php declare(strict_types=1);
/**
 * Error handling functions
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */
if (! defined('SCRIPT_ID')) {
    define('SCRIPT_ID', basename($_SERVER['PHP_SELF'], '.php'));
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

// Is this the webinterface or commandline?
define('IS_WEB', isset($_SERVER['REMOTE_ADDR']));

// Open standard error:
if (! IS_WEB && ! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

// Open log file:
if (defined('LOGFILE')) {
    if ($fp = @fopen(LOGFILE, 'a')) {
        define('STDLOG', $fp);
    } else {
        fwrite(STDERR, "Cannot open log file " . LOGFILE .
            " for appending; continuing without logging.\n");
    }
    unset($fp);
}

// Open syslog connection:
if (defined('SYSLOG')) {
    if (!openlog(SCRIPT_ID, LOG_NDELAY | LOG_PID | LOG_CONS, SYSLOG)) {
        error("cannot open syslog");
    }
}

/**
 * Log a message $string on the loglevel $msglevel.
 * Prepends a timestamp and log to the logfile.
 * If this is the web interface: write to the screen with the right CSS class.
 * If this is the command line: write to Standard Error.
 */
function logmsg(int $msglevel, string $string)
{
    global $verbose, $loglevel;

    // Trim $string to reasonable length to prevent server/browser crashes:
    $string = substr($string, 0, 10000);

    $msec = sprintf("%03d", (int)(explode(' ', microtime())[0]*1000));
    $stamp = "[" . strftime("%b %d %H:%M:%S") . ".$msec] " . SCRIPT_ID .
        (function_exists('posix_getpid') ? "[" . posix_getpid() . "]" : "") .
        ": ";

    if ($msglevel <= $verbose) {
        // if this is the webinterface, print it to stdout, else to stderr
        if (IS_WEB) {
            $msg = htmlspecialchars($string);
            // if this is the API, do not add HTML formatting and send HTTP status code
            // string 'ERROR' parsed by submit client, don't modify!
            if ($msglevel == LOG_ERR) {
                echo "<fieldset class=\"error\"><legend>ERROR</legend> " .
                     $msg . "</fieldset><!-- trigger HTML validator error: --><b>\n";
            } elseif ($msglevel == LOG_WARNING) {
                echo "<fieldset class=\"warning\"><legend>Warning</legend> " .
                    $msg . "</fieldset><!-- trigger HTML validator error: --><b>\n";
            } else {
                echo "<p>" . $msg . "</p>\n";
            }
        } else {
            fwrite(STDERR, $stamp . $string . "\n");
            fflush(STDERR);
        }
    }
    if ($msglevel <= $loglevel) {
        if (defined('STDLOG')) {
            fwrite(STDLOG, $stamp . $string . "\n");
            fflush(STDLOG);
        }
        if (defined('SYSLOG')) {
            syslog($msglevel, $string . "\n");
        }
    }
}

/**
 * Log an error at level LOG_ERROR and exit with exitcode 1.
 */
function error(string $string)
{
    logmsg(LOG_ERR, "error: $string");
    exit(1);
}

/**
 * Log a warning at level LOG_WARNING.
 */
function warning(string $string)
{
    logmsg(LOG_WARNING, "warning: $string");
}

/**
 * Write debugging output using var_dump(). Uses <pre> in web context.
 */
function debug()
{
    if (DEBUG===0) {
        return;
    }

    if (IS_WEB) {
        echo "<pre>\n";
    }
    call_user_func_array('var_dump', func_get_args());
    if (IS_WEB) {
        echo "</pre>\n";
    }
}

/**
 * Handle exceptions by calling error().
 */
function exception_handler(Throwable $e)
{
    error($e->getMessage() . ( (defined('DEBUG') && DEBUG) ? " in " . $e->getFile() . " line " . $e->getLine() : ''));
}

set_exception_handler('exception_handler');
