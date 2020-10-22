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

/* The functions below abstract away the precise time format used
 * internally. We currently use Unix epoch with up to 9 decimals for
 * subsecond precision.
 */

/**
 * Simulate MySQL UNIX_TIMESTAMP() function to create insert queries
 * that do not change when replicated later.
 */
function now() : float
{
    return microtime(true);
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


/* Mapping from REST API endpoints to relevant information:
 * - type: one of 'configuration', 'live', 'aggregate'
 * - url: REST API URL of endpoint relative to baseurl, defaults to '/<endpoint>'
 * - tables: array of database table(s) associated to data, defaults to <endpoint> without 's'
 * - extid: database field for external/API ID, if TRUE same as internal/DB ID.
 *
 */
$API_endpoints = array(
    'contests' => array(
        'type'   => 'configuration',
        'url'    => '',
        'extid'  => true,
    ),
    'judgement-types' => array( // hardcoded in $VERDICTS and the API
        'type'   => 'configuration',
        'tables' => array(),
        'extid'  => true,
    ),
    'languages' => array(
        'type'   => 'configuration',
        'extid'  => 'externalid',
    ),
    'problems' => array(
        'type'   => 'configuration',
        'tables' => array('problem', 'contestproblem'),
        'extid'  => true,
    ),
    'groups' => array(
        'type'   => 'configuration',
        'tables' => array('team_category'),
        'extid'  => true, // FIXME
    ),
    'organizations' => array(
        'type'   => 'configuration',
        'tables' => array('team_affiliation'),
        'extid'  => true,
    ),
    'teams' => array(
        'type'   => 'configuration',
        'tables' => array('team', 'contestteam'),
        'extid'  => true,
    ),
/*
    'team-members' => array(
        'type'   => 'configuration',
        'tables' => array(),
    ),
*/
    'state' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'submissions' => array(
        'type'   => 'live',
        'extid'  => true, // 'externalid,cid' in ICPC-live branch
    ),
    'judgements' => array(
        'type'   => 'live',
        'tables' => array('judging'),
        'extid'  => true,
    ),
    'runs' => array(
        'type'   => 'live',
        'tables' => array('judging_run'),
        'extid'  => true,
    ),
    'clarifications' => array(
        'type'   => 'live',
        'extid'  => true,
    ),
    'awards' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'scoreboard' => array(
        'type'   => 'aggregate',
        'tables' => array(),
    ),
    'event-feed' => array(
        'type'   => 'aggregate',
        'tables' => array('event'),
    ),
    // From here are DOMjudge extensions:
    'users' => array(
        'type'   => 'configuration',
        'url'    => null,
        'extid'  => true,
    ),
    'testcases' => array(
        'type'   => 'configuration',
        'url'    => null,
        'extid'  => true,
    ),
);
// Add defaults to mapping:
foreach ($API_endpoints as $endpoint => $data) {
    if (!array_key_exists('url', $data)) {
        $API_endpoints[$endpoint]['url'] = '/'.$endpoint;
    }
    if (!array_key_exists('tables', $data)) {
        $API_endpoints[$endpoint]['tables'] = array( preg_replace('/s$/', '', $endpoint) );
    }
}

$resturl = $restuser = $restpass = null;

/**
 * This function is copied from judgedaemon.main.php and a quick hack.
 * We should directly call the code that generates the API response.
 */
function read_API_credentials()
{
    global $resturl, $restuser, $restpass;

    $credfile = ETCDIR . '/restapi.secret';
    $credentials = @file($credfile);
    if (!$credentials) {
        error("Cannot read REST API credentials file " . $credfile);
    }
    foreach ($credentials as $credential) {
        if ($credential[0] == '#') {
            continue;
        }
        list($endpointID, $resturl, $restuser, $restpass) = preg_split("/\s+/", trim($credential));
        if ($endpointID==='default') {
            return;
        }
    }
    $resturl = $restuser = $restpass = null;
}

/**
 * Perform a request to the REST API and handle any errors.
 * $url is the part appended to the base DOMjudge $resturl.
 * $verb is the HTTP method to use: GET, POST, PUT, or DELETE
 * $data is the urlencoded data passed as GET or POST parameters.
 * When $failonerror is set to false, any error will be turned into a
 * warning and null is returned.
 * When $asadmin is true and we are doing an internal request (i.e. $G_SYMFONY is defined) perform all requests as an admin
 *
 * This function is duplicated from judge/judgedaemon.main.php.
 */
function API_request(string $url, string $verb = 'GET', string $data = '', bool $failonerror = true, bool $asadmin = false)
{
    global $resturl, $restuser, $restpass, $lastrequest, $G_SYMFONY, $apiFromInternal;
    if (isset($G_SYMFONY)) {
        /** @var \App\Service\DOMJudgeService $G_SYMFONY */
        // Perform an internal Symfony request to the API
        logmsg(LOG_DEBUG, "API internal request $verb $url");

        $apiFromInternal = true;
        $url = 'http://localhost/api'. $url;
        $httpKernel = $G_SYMFONY->getHttpKernel();
        parse_str($data, $parsedData);

        // Our API checks $_SERVER['REQUEST_METHOD'], $_GET and $_POST but Symfony does not overwrite it, so do this manually
        $origMethod = $_SERVER['REQUEST_METHOD'];
        $origPost = $_POST;
        $origGet = $_GET;
        $_POST = [];
        $_GET = [];
        // TODO: other verbs
        if ($verb === 'GET') {
            $_GET = $parsedData;
        } elseif ($verb === 'POST') {
            $_POST = $parsedData;
        }
        $_SERVER['REQUEST_METHOD'] = $verb;

        $G_SYMFONY->withAllRoles(function() use ($httpKernel, $parsedData, $verb, $url, &$response) {
            $request  = \Symfony\Component\HttpFoundation\Request::create($url, $verb, $parsedData);
            $response = $httpKernel->handle($request, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
        });

        // Set back the request method and superglobals, if other code still wants to use it
        $_SERVER['REQUEST_METHOD'] = $origMethod;
        $_GET = $origGet;
        $_POST = $origPost;

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $errstr = "executing internal $verb request to url " . $url .
                ": http status code: " . $status . ", response: " . $response;
            if ($failonerror) {
                error($errstr);
            } else {
                logmsg(LOG_WARNING, $errstr);
                return null;
            }
        }

        return $response->getContent();
    }

    if ($resturl === null) {
        read_API_credentials();
        if ($resturl === null) {
            error("could not initialize REST API credentials");
        }
    }

    logmsg(LOG_DEBUG, "API request $verb $url");

    $url = $resturl . $url;
    if ($verb == 'GET' && !empty($data)) {
        $url .= '?' . $data;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $restuser . ":" . $restpass);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($verb == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
        }
    } elseif ($verb == 'PUT' || $verb == 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
    }
    if ($verb == 'POST' || $verb == 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $errstr = "Error while executing curl $verb to url " . $url . ": " . curl_error($ch);
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            return null;
        }
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status < 200 || $status >= 300) {
        $errstr = "executing internal $verb request to url " . $url .
            ": http status code: " . $status . ", response: " . $response;
        if ($failonerror) {
            error($errstr);
        } else {
            logmsg(LOG_WARNING, $errstr);
            return null;
        }
    }

    curl_close($ch);
    return $response;
}
