<?php declare(strict_types=1);
/**
 * Request a yet unjudged submission from the domserver, judge it, and pass
 * the results back to the domserver.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */
if (isset($_SERVER['REMOTE_ADDR'])) {
    die("Commandline use only");
}

require(ETCDIR . '/judgehost-config.php');

$endpoints = [];
$domjudge_config = [];

function judging_directory(string $workdirpath, array $judging)
{
    return "$workdirpath/$judging[cid]/$judging[submitid]/$judging[judgingid]";
}

function read_credentials()
{
    global $endpoints;

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
        if (array_key_exists($endpointID, $endpoints)) {
            error("Error parsing REST API credentials. Duplicate endpoint ID '$endpointID'.");
        }
        $endpoints[$endpointID] = [
            "url" => $resturl,
            "user" => $restuser,
            "pass" => $restpass,
            "waiting" => false,
            "errorred" => false,
            "last_attempt" => -1,
        ];
    }
    if (count($endpoints) <= 0) {
        error("Error parsing REST API credentials.");
    }
}


function setup_curl_handle(string $restuser, string $restpass)
{
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
    curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl_handle, CURLOPT_USERPWD, $restuser . ":" . $restpass);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    return $curl_handle;
}

function close_curl_handles()
{
    global $endpoints;
    foreach($endpoints as $id => $endpoint) {
        if ( ! empty($endpoint['curl']) ) {
            curl_close($endpoint['curl']);
            unset($endpoints[$id]['curl']);
        }
    }
}

/**
 * Perform a request to the REST API and handle any errors.
 * $url is the part appended to the base DOMjudge $resturl.
 * $verb is the HTTP method to use: GET, POST, PUT, or DELETE
 * $data is the urlencoded data passed as GET or POST parameters.
 * When $failonerror is set to false, any error will be turned into a
 * warning and null is returned.
 */
$lastrequest = '';
function request(string $url, string $verb = 'GET', string $data = '', bool $failonerror = true)
{
    global $endpoints, $endpointID, $lastrequest;

    // Don't flood the log with requests for new judgings every few seconds.
    if (strpos($url, 'judgehosts/next-judging') === 0 && $verb==='POST') {
        if ($lastrequest!==$url) {
            logmsg(LOG_DEBUG, "API request $verb $url");
            $lastrequest = $url;
        }
    } else {
        logmsg(LOG_DEBUG, "API request $verb $url");
        $lastrequest = $url;
    }

    $url = $endpoints[$endpointID]['url'] . "/" . $url;
    $curl_handle = $endpoints[$endpointID]['ch'];
    if ($verb == 'GET') {
        $url .= '?' . $data;
    }

    curl_setopt($curl_handle, CURLOPT_URL, $url);

    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $verb);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, []);
    if ($verb == 'POST') {
        curl_setopt($curl_handle, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
        }
    } else {
        curl_setopt($curl_handle, CURLOPT_POST, false);
    }
    if ($verb == 'POST' || $verb == 'PUT') {
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);
    } else {
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, null);
    }

    $response = curl_exec($curl_handle);
    if ($response === false) {
        $errstr = "Error while executing curl $verb to url " . $url . ": " . curl_error($curl_handle);
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            $endpoints[$endpointID]['errorred'] = true;
            return null;
        }
    }
    $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    if ($status < 200 || $status >= 300) {
        if ($status == 401) {
            $errstr = "Authentication failed (error $status) while contacting $url. " .
                "Check credentials in restapi.secret.";
        } else {
            $errstr = "Error while executing curl $verb to url " . $url .
                ": http status code: " . $status .
                ", request size = " . strlen($data) .
                ", response: " . $response;
        }
        if ($failonerror) {
            error($errstr);
        } else {
            warning($errstr);
            $endpoints[$endpointID]['errorred'] = true;
            return null;
        }
    }

    if ( $endpoints[$endpointID]['errorred'] ) {
        $endpoints[$endpointID]['errorred'] = false;
        $endpoints[$endpointID]['waiting'] = false;
        logmsg(LOG_NOTICE, "Reconnected to endpoint $endpointID.");
    }

    return $response;
}

/**
 * Retrieve the configuration through the REST API.
 */
function djconfig_refresh() : void
{
    global $domjudge_config;

    $res = request('config', 'GET');
    $res = dj_json_decode($res);
    $domjudge_config = $res;
}


/**
 * Retrieve a value from the DOMjudge configuration.
 */
function djconfig_get_value(string $name)
{
    global $domjudge_config;
    if (empty($domjudge_config)) {
        error("DOMjudge config not initialised before call to djconfig_get_value()");
    }
    return $domjudge_config[$name];
}

/**
 * Encode file contents for POST-ing to REST API.
 * Returns contents of $file (optionally limited in size, see
 * dj_file_get_contents) as encoded string.
 * $sizelimit can be set to the following values:
 * - TRUE: use the 'output_storage_limit' configuration setting
 * - positive integer: limit to this many bytes
 * - FALSE or -1: no size limit imposed
 */
function rest_encode_file(string $file, $sizelimit = true) : string
{
    if ($sizelimit===true) {
        $maxsize = (int) djconfig_get_value('output_storage_limit');
    } elseif ($sizelimit===false || $sizelimit==-1) {
        $maxsize = -1;
    } elseif (is_int($sizelimit) && $sizelimit>0) {
        $maxsize = $sizelimit;
    } else {
        error("Invalid argument sizelimit = '$sizelimit' specified.");
    }
    return urlencode(base64_encode(dj_file_get_contents($file, $maxsize)));
}

$waittime = 5;

define('SCRIPT_ID', 'judgedaemon');
define('PIDFILE', RUNDIR.'/judgedaemon.pid');
define('CHROOT_SCRIPT', 'chroot-startstop.sh');

function usage()
{
    echo "Usage: " . SCRIPT_ID . " [OPTION]...\n" .
        "Start the judgedaemon.\n\n" .
        "  -d       daemonize after startup\n" .
        "  -n <id>  daemon number\n" .
        "  -v       set verbosity to LEVEL (syslog levels)\n" .
        "  -h       display this help and exit\n" .
        "  -V       output version information and exit\n\n";
    exit;
}

function read_judgehostlog(int $n = 20) : string
{
    ob_start();
    passthru("tail -$n " . escapeshellarg(LOGFILE));
    return trim(ob_get_clean());
}

// fetches new executable from database if necessary
// runs build to compile executable
// returns array with absolute path to run script and possibly error message
function fetch_executable(
    string $workdirpath, string $execid, string $md5sum, bool $combined_run_compare = false) : array
{
    $execpath = "$workdirpath/executable/" . $execid;
    $execmd5path = $execpath . "/md5sum";
    $execdeploypath = $execpath . "/.deployed";
    $execbuildpath = $execpath . "/build";
    $execrunpath = $execpath . "/run";
    $execzippath = $execpath . "/executable.zip";
    if (empty($md5sum)) {
        return array(null, "unknown executable '" . $execid . "' specified");
    }
    if (!file_exists($execpath) || !file_exists($execmd5path) ||
        !file_exists($execdeploypath) ||
        dj_file_get_contents($execmd5path) !== $md5sum) {
        logmsg(LOG_INFO, "Fetching new executable '" . $execid . "'");
        system("rm -rf $execpath");
        system("mkdir -p '$execpath'", $retval);
        if ($retval!=0) {
            error("Could not create directory '$execpath'");
        }
        $content = request(sprintf('executables/%s', $execid), 'GET', '');
        $content = base64_decode(dj_json_decode($content));
        if (file_put_contents($execzippath, $content) === false) {
            error("Could not create executable zip file in $execpath");
        }
        unset($content);
        if (md5_file($execzippath) !== $md5sum) {
            error("Zip file corrupted during download.");
        }
        if (file_put_contents($execmd5path, $md5sum) === false) {
            error("Could not write md5sum to file.");
        }

        logmsg(LOG_DEBUG, "Unzipping");
        system("unzip -Z $execzippath | grep -q ^l", $retval);
        if ($retval===0) {
            error("Zipfile $execzippath contains symlinks");
        }
        system("unzip -j -q -d $execpath $execzippath", $retval);
        if ($retval!=0) {
            error("Could not unzip zipfile in $execpath");
        }

        $do_compile = true;
        if (!file_exists($execbuildpath)) {
            if (file_exists($execrunpath)) {
                // 'run' already exists, 'build' does not => don't compile anything
                logmsg(LOG_DEBUG, "'run' exists without 'build', we are done");
                $do_compile = false;
            } else {
                // detect lang and write build file
                $langexts = array(
                        'c' => array('c'),
                        'cpp' => array('cpp', 'C', 'cc'),
                        'java' => array('java'),
                        'py' => array('py', 'py2', 'py3')
                );
                $buildscript = "#!/bin/sh\n\n";
                $execlang = false;
                $source = "";
                foreach ($langexts as $lang => $langext) {
                    if (($handle = opendir($execpath)) === false) {
                        error("Could not open $execpath");
                    }
                    while (($file = readdir($handle)) !== false) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        if (in_array($ext, $langext)) {
                            $execlang = $lang;
                            $source = $file;
                            break;
                        }
                    }
                    closedir($handle);
                    if ($execlang !== false) {
                        break;
                    }
                }
                if ($execlang === false) {
                    return array(null, "executable must either provide an executable file named 'build' or a C/C++/Java or Python file.");
                }
                switch ($execlang) {
                case 'c':
                    $buildscript .= "gcc -Wall -O2 -std=gnu11 '$source' -o $execrunpath -lm\n";
                    break;
                case 'cpp':
                    $buildscript .= "g++ -Wall -O2 -std=gnu++17 '$source' -o $execrunpath\n";
                    break;
                case 'java':
                    $source = basename($source, ".java");
                    $buildscript .= "javac -cp $execpath -d $execpath '$source'.java\n";
                    $buildscript .= "echo '#!/bin/sh' > run\n";
                    // no main class detection here
                    $buildscript .= "echo 'java -cp $execpath '$source' >> run\n";
                    break;
                case 'py':
                    $buildscript .= "echo '#!/bin/sh' > run\n";
                    $buildscript .= "echo 'python '$source' >> run\n";
                    break;
                }
                if ( $combined_run_compare ) {
                    $buildscript .= <<<'EOT'
mv run runjury

cat <<'EOF' > run
#!/bin/sh

# Run wrapper-script to be called from 'testcase_run.sh'.
#
# This script is meant to simplify writing interactive problems where the
# contestants' solution bi-directionally communicates with a jury program, e.g.
# while playing a two player game.
#
# Usage: $0 <testin> <progout> <testout> <metafile> <feedbackdir> <program>...
#
# <testin>      File containing test-input.
# <testout>     File containing test-output.
# <progout>     File where to write solution output. Note: this is unused.
# <feedbackdir> Directory to write jury feedback files to.
# <program>     Command and options of the program to be run.

# A jury-written program called 'runjury' should be available; this program
# will normally be compiled by the build script in the validator directory.
# This program should communicate with the contestants' program to provide
# input and read output via stdin/stdout. This wrapper script handles the setup
# of bi-directional pipes. The jury program should accept the following calling
# syntax:
#
#    runjury <testin> <testout> <feedbackdir> < <output of the program>
#
# The jury program should exit with exitcode 42 if the submissions is accepted,
# 43 otherwise.

TESTIN="$1";  shift
PROGOUT="$1"; shift
TESTOUT="$1"; shift
META="$1"; shift
FEEDBACK="$1"; shift

MYDIR=$(dirname $0)

# Run the program while redirecting its stdin/stdout to 'runjury' via
# 'runpipe'. Note that "$@" expands to separate, quoted arguments.
exec ../dj-bin/runpipe ${DEBUG:+-v} -M "$META" -o "$PROGOUT" "$MYDIR/runjury" "$TESTIN" "$TESTOUT" "$FEEDBACK" = "$@"
EOF

chmod +x run

EOT;
                }
                if (file_put_contents($execbuildpath, $buildscript) === false) {
                    error("Could not write file 'build' in $execpath");
                }
                chmod($execbuildpath, 0755);
            }
        } elseif (!is_executable($execbuildpath)) {
            return array(null, "Invalid executable, file 'build' exists but is not executable.");
        }

        if ($do_compile) {
            logmsg(LOG_DEBUG, "Compiling");
            $olddir = getcwd();
            chdir($execpath);
            system("./build >> " . LOGFILE . " 2>&1", $retval);
            if ($retval!=0) {
                return array(null, "Could not run ./build in $execpath.");
            }
            chdir($olddir);
        }
        if (!file_exists($execrunpath) || !is_executable($execrunpath)) {
            return array(null, "Invalid build file, must produce an executable file 'run'.");
        }
    }
    // Create file to mark executable successfully deployed.
    touch($execdeploypath);

    return array($execrunpath, null);
}

$options = getopt("dv:n:hV");
// FIXME: getopt doesn't return FALSE on parse failure as documented!
if ($options===false) {
    echo "Error: parsing options failed.\n";
    usage();
}
if (isset($options['d'])) {
    $options['daemon']  = $options['d'];
}
if (isset($options['v'])) {
    $options['verbose'] = $options['v'];
}
if (isset($options['n'])) {
    $options['daemonid'] = $options['n'];
}

if (isset($options['V'])) {
    version();
}
if (isset($options['h'])) {
    usage();
}

if ( posix_getuid()==0 || posix_geteuid()==0 ) {
    echo "This program should not be run as root.\n";
    exit(1);
}

$myhost = trim(`hostname | cut -d . -f 1`);
if (isset($options['daemonid'])) {
    if (preg_match('/^\d+$/', $options['daemonid'])) {
        $myhost = $myhost . "-" . $options['daemonid'];
    } else {
        echo "Invalid value for daemonid, must be positive integer.\n";
        exit(1);
    }
}

define('LOGFILE', LOGDIR.'/judge.'.$myhost.'.log');
require(LIBDIR . '/lib.error.php');
require(LIBDIR . '/lib.misc.php');

$verbose = LOG_INFO;
if (isset($options['verbose'])) {
    if (preg_match('/^\d+$/', $options['verbose'])) {
        $verbose = $options['verbose'];
    } else {
        echo "Invalid value for verbose, must be positive integer\n";
        exit(1);
    }
}

if (DEBUG & DEBUG_JUDGE) {
    $verbose = LOG_DEBUG;
    putenv('DEBUG=1');
}

$runuser = RUNUSER;
if (isset($options['daemonid'])) {
    $runuser .= '-' . $options['daemonid'];
}

// Set static environment variables for passing path configuration
// to called programs:
putenv('DJ_BINDIR='      . BINDIR);
putenv('DJ_ETCDIR='      . ETCDIR);
putenv('DJ_JUDGEDIR='    . JUDGEDIR);
putenv('DJ_LIBDIR='      . LIBDIR);
putenv('DJ_LIBJUDGEDIR=' . LIBJUDGEDIR);
putenv('DJ_LOGDIR='      . LOGDIR);
putenv('RUNUSER='        . $runuser);
putenv('RUNGROUP='       . RUNGROUP);

foreach ($EXITCODES as $code => $name) {
    $var = 'E_' . strtoupper(str_replace('-', '_', $name));
    putenv($var . '=' . $code);
}

// Pass SYSLOG variable via environment for compare program
if (defined('SYSLOG') && SYSLOG) {
    putenv('DJ_SYSLOG=' . SYSLOG);
}

if (! posix_getpwnam($runuser)) {
    error("runuser $runuser does not exist.");
}
$output = array();
exec("ps -u '$runuser' -o pid= -o comm=", $output, $retval);
if (count($output) != 0) {
    error("found processes still running as '$runuser', check manually:\n" .
          implode("\n", $output));
}

logmsg(LOG_NOTICE, "Judge started on $myhost [DOMjudge/".DOMJUDGE_VERSION."]");

initsignals();

read_credentials();

// Set umask to allow group,other access, as this is needed for the
// unprivileged user.
umask(0022);

// Check basic prerequisites for chroot at judgehost startup
logmsg(LOG_INFO, "executing chroot script: '".CHROOT_SCRIPT." check'");
system(LIBJUDGEDIR.'/'.CHROOT_SCRIPT.' check', $retval);
if ($retval!=0) {
    error("chroot sanity check exited with exitcode $retval");
}

// If all startup done, daemonize
if (isset($options['daemon'])) {
    daemonize(PIDFILE);
}

foreach ($endpoints as $id=>$endpoint) {
    $endpointID = $id;
    registerJudgehost($myhost);
}

// Populate the DOMjudge configuration initially
djconfig_refresh();

// Constantly check API for unjudged submissions
$endpointIDs = array_keys($endpoints);
$currentEndpoint = 0;
while (true) {

    // If all endpoints are waiting, sleep for a bit
    $dosleep = true;
    foreach ($endpoints as $id=>$endpoint) {
        if ($endpoint['errorred']) {
            $endpointID = $id;
            registerJudgehost($myhost);
        }
        if (!$endpoint['waiting']) {
            $dosleep = false;
            break;
        }
    }
    // Sleep only if everything is "waiting" and only if we're looking at the first endpoint again
    if ($dosleep && $currentEndpoint==0) {
        sleep($waittime);
    }

    // Increment our currentEndpoint pointer
    $currentEndpoint = ($currentEndpoint + 1) % count($endpoints);
    $endpointID = $endpointIDs[$currentEndpoint];
    $workdirpath = JUDGEDIR . "/$myhost/endpoint-$endpointID";

    // Check whether we have received an exit signal
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    if ($exitsignalled) {
        logmsg(LOG_NOTICE, "Received signal, exiting.");
        close_curl_handles();
        exit;
    }

    if ($endpoints[$endpointID]['errorred']) {
        continue;
    }

    if ($endpoints[$endpointID]['waiting'] === false) {
        // Check for available disk space
        $free_space = disk_free_space(JUDGEDIR);
        $allowed_free_space  = djconfig_get_value('diskspace_error'); // in kB
        if ($free_space < 1024*$allowed_free_space) {
            $free_abs = sprintf("%01.2fGB", $free_space / (1024*1024*1024));
            logmsg(LOG_ERR, "Low on disk space: $free_abs free, clean up or " .
                    "change 'diskspace error' value in config before resolving this error.");

            $disabled = dj_json_encode(array(
                'kind' => 'judgehost',
                'hostname' => $myhost));
            $judgehostlog = read_judgehostlog();
            $error_id = request(
                'judgehosts/internal-error',
                'POST',
                'description=' . urlencode("low on disk space on $myhost") .
                '&judgehostlog=' . urlencode(base64_encode($judgehostlog)) .
                '&disabled=' . urlencode($disabled),
                false
            );
            logmsg(LOG_ERR, "=> internal error " . $error_id);
        }
    }

    // Request open submissions to judge. Any errors will be treated as
    // non-fatal: we will just keep on retrying in this loop.
    $url = sprintf('judgehosts/next-judging/%s', urlencode($myhost));
    $judging = request($url, 'POST', '', false);
    // If $judging is null, an error occurred; don't try to decode.
    if (!is_null($judging)) {
        $row = dj_json_decode($judging);
    }

    // nothing returned -> no open submissions for us
    if (empty($row)) {
        if (! $endpoints[$endpointID]["waiting"]) {
            logmsg(LOG_INFO, "No submissions in queue (for endpoint $endpointID), waiting...");
            $endpoints[$endpointID]["waiting"] = true;
        }
        continue;
    }

    // we have gotten a submission for judging
    $endpoints[$endpointID]["waiting"] = false;

    logmsg(LOG_NOTICE, "Judging submission s$row[submitid] (endpoint $endpointID) ".
           "(t$row[teamid]/p$row[probid]/$row[langid]), id j$row[judgingid]...");

    judge($row);

    // Check if we were interrupted while judging, if so, exit (to avoid sleeping)
    if ($exitsignalled) {
        logmsg(LOG_NOTICE, "Received signal, exiting.");
        close_curl_handles();
        exit;
    }

    // restart the judging loop
}

function registerJudgehost($myhost)
{
    global $endpoints, $endpointID;
    $endpoint = &$endpoints[$endpointID];

    // Only try to register every 30s.
    $now = now();
    if ($now - $endpoint['last_attempt'] < 30) {
        $endpoint['waiting'] = true;
        return;
    }
    $endpoint['last_attempt'] = $now;

    logmsg(LOG_NOTICE, "Registering judgehost on endpoint $endpointID: " . $endpoint['url']);
    $endpoints[$endpointID]['ch'] = setup_curl_handle($endpoint['user'], $endpoint['pass']);

    // Create directory where to test submissions
    $workdirpath = JUDGEDIR . "/$myhost/endpoint-$endpointID";
    system("mkdir -p $workdirpath/testcase", $retval);
    if ($retval != 0) {
        error("Could not create $workdirpath");
    }
    chmod("$workdirpath/testcase", 0700);

    // Auto-register judgehost.
    // If there are any unfinished judgings in the queue in my name,
    // they will not be finished. Give them back.
    $unfinished = request('judgehosts', 'POST', 'hostname=' . urlencode($myhost), false);
    if ($unfinished === NULL) {
        logmsg(LOG_WARNING, "Registering judgehost on endpoint $endpointID failed.");
    } else {
        $unfinished = dj_json_decode($unfinished);
        foreach ($unfinished as $jud) {
            $workdir = judging_directory($workdirpath, $jud);
            @chmod($workdir, 0700);
            logmsg(LOG_WARNING, "Found unfinished judging j" . $jud['judgingid'] .
                " in my name; given back");
        }
    }
}

function disable(string $kind, string $idcolumn, $id, string $description, int $judgingid, string $cid, $extra_log = null)
{
    $disabled = dj_json_encode(array(
        'kind' => $kind,
        $idcolumn => $id));
    $judgehostlog = read_judgehostlog();
    if (isset($extra_log)) {
        $judgehostlog .= "\n\n"
            . "--------------------------------------------------------------------------------"
            . "\n\n"
            . $extra_log;
    }
    $error_id = request(
        'judgehosts/internal-error',
        'POST',
        'judgingid=' . urlencode((string)$judgingid) .
        '&cid=' . urlencode($cid) .
        '&description=' . urlencode($description) .
        '&judgehostlog=' . urlencode(base64_encode($judgehostlog)) .
        '&disabled=' . urlencode($disabled)
    );
}

function read_metadata(string $filename)
{
    if (!is_readable($filename)) return null;

    // Don't quite treat it as YAML, but simply key/value pairs.
    $contents = explode("\n", dj_file_get_contents($filename));
    $res = [];
    foreach($contents as $line) {
        if (strpos($line, ":") !== false) {
            list($key, $value) = explode(":", $line, 2);
            $res[$key] = trim($value);
        }
    }

    return $res;
}

function send_unsent_judging_runs($unsent_judging_runs, $judgingid)
{
    global $myhost;

    return request(
        sprintf('judgehosts/add-judging-run/%s/%s', urlencode($myhost),
                urlencode((string)$judgingid)),
        'POST',
        'batch=' . json_encode($unsent_judging_runs),
        false
    );
}

function judge(array $row)
{
    global $EXITCODES, $myhost, $options, $workdirpath, $exitsignalled, $gracefulexitsignalled;

    // refresh config at start of judge run
    djconfig_refresh();

    // Set configuration variables for called programs
    putenv('CREATE_WRITABLE_TEMP_DIR=' . (CREATE_WRITABLE_TEMP_DIR ? '1' : ''));
    putenv('SCRIPTTIMELIMIT='          . djconfig_get_value('script_timelimit'));
    putenv('SCRIPTMEMLIMIT='           . djconfig_get_value('script_memory_limit'));
    putenv('SCRIPTFILELIMIT='          . djconfig_get_value('script_filesize_limit'));
    putenv('MEMLIMIT='                 . $row['memlimit']);
    putenv('FILELIMIT='                . $row['outputlimit']);
    putenv('PROCLIMIT='                . djconfig_get_value('process_limit'));
    if ($row['entry_point'] !== null) {
        putenv('ENTRY_POINT=' . $row['entry_point']);
    } else {
        putenv('ENTRY_POINT');
    }
    $output_storage_limit = (int) djconfig_get_value('output_storage_limit');

    $cpuset_opt = "";
    if (isset($options['daemonid'])) {
        $cpuset_opt = "-n ${options['daemonid']}";
    }

    // create workdir for judging
    $workdir = judging_directory($workdirpath, $row);

    logmsg(LOG_INFO, "Working directory: $workdir");

    // If a database gets reset without removing the judging
    // directories, we might hit an old directory: rename it.
    if (file_exists($workdir)) {
        $oldworkdir = $workdir . '-old-' . getmypid() . '-' . strftime('%Y-%m-%d_%H:%M');
        if (!rename($workdir, $oldworkdir)) {
            error("Could not rename stale working directory to '$oldworkdir'");
        }
        @chmod($oldworkdir, 0700);
        warning("Found stale working directory; renamed to '$oldworkdir'");
    }

    system("mkdir -p '$workdir/compile'", $retval);
    if ($retval != 0) {
        error("Could not create '$workdir/compile'");
    }

    // Make sure the workdir is accessible for the domjudge-run user.
    // Will be revoked again after this run finished.
    chmod($workdir, 0755);

    if (!chdir($workdir)) {
        error("Could not chdir to '$workdir'");
    }

    // Get the source code from the DB and store in local file(s)
    $url = sprintf('contests/%s/submissions/%s/source-code',
                   urlencode((string)$row['cid']), $row['submitid']);
    $sources = request($url, 'GET', '');
    $sources = dj_json_decode($sources);
    $files = array();
    $hasFiltered = false;
    foreach ($sources as $source) {
        $srcfile = "$workdir/compile/$source[filename]";
        $file = $source['filename'];
        if ($row['filter_compiler_files']) {
            $picked = false;
            foreach ($row['language_extensions'] as $extension) {
                $extensionLength = strlen($extension);
                if (substr($file, -$extensionLength) === $extension) {
                    $files[] = "'$file'";
                    $picked = true;
                    break;
                }
            }
            if (!$picked) {
                $hasFiltered = true;
            }
        } else {
            $files[] = "'$file'";
        }
        if (file_put_contents($srcfile, base64_decode($source['source'])) === false) {
            error("Could not create $srcfile");
        }
    }

    if (empty($files) && $hasFiltered) {
        $message = 'No files with allowed extensions found to pass to compiler. Allowed extensions: ' . implode(', ', $row['language_extensions']);
        $args = 'compile_success=0' .
                '&output_compile=' . urlencode(base64_encode($message));

        $url = sprintf('judgehosts/update-judging/%s/%s', urlencode($myhost), urlencode((string)$row['judgingid']));
        request($url, 'PUT', $args);

        // revoke readablity for domjudge-run user to this workdir
        chmod($workdir, 0700);
        logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$row[judgingid]: compile error");
        return;
    }

    if (count($files)==0) {
        error("No submission files could be downloaded.");
    }

    if (empty($row['compile_script'])) {
        error("No compile script specified for language " . $row['langid'] . ".");
    }

    list($execrunpath, $error) = fetch_executable(
        $workdirpath,
        $row['compile_script'],
        $row['compile_script_md5sum']
    );
    if (isset($error)) {
        logmsg(LOG_ERR, "fetching executable failed for compile script '" . $row['compile_script'] . "': " . $error);
        $description = $row['compile_script'] . ': fetch, compile, or deploy of compile script failed.';
        disable('language', 'langid', $row['langid'], $description, $row['judgingid'], (string)$row['cid']);
        return;
    }

    // Compile the program.
    system(LIBJUDGEDIR . "/compile.sh $cpuset_opt '$execrunpath' '$workdir' " .
           implode(' ', $files), $retval);

    if (is_readable($workdir . '/compile.out')) {
        $compile_output = dj_file_get_contents($workdir . '/compile.out', 50000);
    }
    if (empty($compile_output) && is_readable($workdir . '/compile.tmp')) {
        $compile_output = dj_file_get_contents($workdir . '/compile.tmp', 50000);
    }

    // Try to read metadata from file
    $metadata = read_metadata($workdir . '/compile.meta');
    if (isset($metadata['internal-error'])) {
        alert('error');
        $internalError = $metadata['internal-error'];
        $compile_output .= "\n--------------------------------------------------------------------------------\n\n".
            "Internal errors reported:\n".$internalError;

        if (preg_match('/^compile script: /', $internalError)) {
            $internalError = preg_replace('/^compile script: /', '', $internalError);
            $description = "The compile script returned an error: $internalError";
            disable('language', 'langid', $row['langid'], $description, $row['judgingid'], (string)$row['cid'], $compile_output);
        } else {
            $description = "Running compile.sh caused an error/crash: $internalError";
            disable('judgehost', 'hostname', $myhost, $description, $row['judgingid'], (string)$row['cid'], $compile_output);
        }
        logmsg(LOG_ERR, $description);
        // revoke readablity for domjudge-run user to this workdir
        chmod($workdir, 0700);
        return;
    }

    // What does the exitcode mean?
    if (! isset($EXITCODES[$retval])) {
        alert('error');
        logmsg(LOG_ERR, "Unknown exitcode from compile.sh for s$row[submitid]: $retval");
        $description = "compile script '" . $row['compile_script'] . "' returned exit code " . $retval;
        disable('language', 'langid', $row['langid'], $description, $row['judgingid'], (string)$row['cid'], $compile_output);
        // revoke readablity for domjudge-run user to this workdir
        chmod($workdir, 0700);
        return;
    }
    $compile_success = ($EXITCODES[$retval]==='correct');

    // pop the compilation result back into the judging table
    $args = 'compile_success=' . $compile_success .
        '&output_compile=' . rest_encode_file($workdir . '/compile.out', $output_storage_limit);
    if (isset($metadata['entry_point'])) {
        $args .= '&entry_point=' . urlencode($metadata['entry_point']);
    }

    $url = sprintf('judgehosts/update-judging/%s/%s', urlencode($myhost), urlencode((string)$row['judgingid']));
    request($url, 'PUT', $args);

    // compile error: our job here is done
    if (! $compile_success) {
        // revoke readablity for domjudge-run user to this workdir
        chmod($workdir, 0700);
        logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$row[judgingid]: compile error");
        return;
    }

    // create chroot environment
    logmsg(LOG_INFO, "executing chroot script: '".CHROOT_SCRIPT." start'");
    system(LIBJUDGEDIR.'/'.CHROOT_SCRIPT.' start', $retval);
    if ($retval!=0) {
        error("chroot script exited with exitcode $retval");
    }

    $overshoot = djconfig_get_value('timelimit_overshoot');

    $totalcases = 0;
    $lastcase_correct = true;
    $unsent_judging_runs = array();
    $last_sent = now();
    $outstanding_data = 0;
    $update_every_X_seconds = djconfig_get_value('update_judging_seconds');

    // There is no guarantee in which order the API returns the data, so let's
    // order it here by rank.
    ksort($row['testcases']);

    foreach ($row['testcases'] as $tc) {
        // Check whether we have received an exit signal(but not a graceful exit signal)
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($exitsignalled && !$gracefulexitsignalled) {
            logmsg(LOG_NOTICE, "Received HARD exit signal, aborting current judging.");

            // Make sure the domserver knows that we didn't finish this judging
            $unfinished = request('judgehosts', 'POST', 'hostname=' . urlencode($myhost));
            $unfinished = dj_json_decode($unfinished);
            foreach ($unfinished as $jud) {
                logmsg(LOG_WARNING, "Aborted judging j" . $jud['judgingid'] .
                       " due to signal");
            }

            // Break, not exit so we cleanup nicely
            break;
        }

        if (!$lastcase_correct) {
            // get the next testcase
            $url = sprintf('testcases/next-to-judge/%s', $row['judgingid']);
            $testcase = request($url, 'GET', '');
            $tc = dj_json_decode($testcase);
            if ($tc === null) {
                $disabled = dj_json_encode(array(
                    'kind' => 'problem',
                    'probid' => $row['probid']));
                $judgehostlog = read_judgehostlog();
                $error_id = request(
                    'judgehosts/internal-error',
                    'POST',
                    'judgingid=' . urlencode((string)$row['judgingid']) .
                    '&cid=' . urlencode((string)$row['cid']) .
                    '&description=' . urlencode("no test cases found") .
                    '&judgehostlog=' . urlencode(base64_encode($judgehostlog)) .
                    '&disabled=' . urlencode($disabled)
                );
                logmsg(LOG_ERR, "No testcases found for p$row[probid] => internal error " . $error_id);
                break;
            }

            // empty means: no more testcases for this judging.
            if (empty($tc)) {
                break;
            }
        }

        $totalcases++;
        logmsg(LOG_DEBUG, "Running testcase $tc[rank]...");
        $testcasedir = $workdir . "/testcase" . sprintf('%03d', $tc['rank']);
        $tcfile = fetchTestcase($row, $workdirpath, $tc['rank']);
        if ($tcfile === NULL) {
            // error while fetching testcase
            return;
        }

        // Copy program with all possible additional files to testcase
        // dir. Use hardlinks to preserve space with big executables.
        $programdir = $testcasedir . '/execdir';
        system("mkdir -p '$programdir'", $retval);
        if ($retval!=0) {
            error("Could not create directory '$programdir'");
        }

        system("cp -PRl '$workdir'/compile/* '$programdir'", $retval);
        if ($retval!=0) {
            error("Could not copy program to '$programdir'");
        }

        // do the actual test-run
        $hardtimelimit = $row['maxruntime'] +
                         overshoot_time($row['maxruntime'], $overshoot);

        list($run_runpath, $error) =
            fetch_executable($workdirpath, $row['run'], $row['run_md5sum'], $row['combined_run_compare']);
        if (isset($error)) {
            logmsg(LOG_ERR, "fetching executable failed for run script '" . $row['run'] . "': " . $error);
            $description = $row['run'] . ': fetch, compile, or deploy of run script failed.';
            disable('problem', 'probid', $row['probid'], $description, $row['judgingid'], (string)$row['cid']);
            return;
        }

        if ($row['combined_run_compare']) {
            // set to empty string to signal the testcase_run script that the
            // run script also acts as compare script
            $compare_runpath = '';
        } else {
            list($compare_runpath, $error) = fetch_executable($workdirpath, $row['compare'], $row['compare_md5sum']);
            if (isset($error)) {
                logmsg(LOG_ERR, "fetching executable failed for compare script '" . $row['compare'] . "': " . $error);
                $description = $row['compare'] . ': fetch, compile, or deploy of validation script failed.';
                disable('problem', 'probid', $row['probid'], $description, $row['judgingid'], (string)$row['cid']);
                return;
            }
        }

        system(LIBJUDGEDIR . "/testcase_run.sh $cpuset_opt $tcfile[input] $tcfile[output] " .
               "$row[maxruntime]:$hardtimelimit '$testcasedir' " .
               "'$run_runpath' '$compare_runpath' '$row[compare_args]'", $retval);

        // what does the exitcode mean?
        if (! isset($EXITCODES[$retval])) {
            alert('error');
            error("Unknown exitcode from testcase_run.sh for s$row[submitid], " .
                  "testcase $tc[rank]: $retval");
        }
        $result = $EXITCODES[$retval];

        // Try to read metadata from file
        $runtime = null;
        $metadata = read_metadata($testcasedir . '/program.meta');

        if (isset($metadata['time-used'])) {
            $runtime = @$metadata[$metadata['time-used']];
        }

        if ($result === 'compare-error') {
            logmsg(LOG_ERR, "comparing failed for compare script '" . $row['compare'] . "'");
            disable('problem', 'probid', $row['probid'], "compare script '" . $row['compare'] . "' crashed", $row['judgingid'], (string)$row['cid']);
            return;
        }

        $lastcase_correct = $result === 'correct';

        $new_judging_run = array(
            'testcaseid' => urlencode((string)$tc['testcaseid']),
            'runresult' => urlencode($result),
            'runtime' => urlencode((string)$runtime),
            'output_run'   => rest_encode_file($testcasedir . '/program.out', false),
            'output_error' => rest_encode_file($testcasedir . '/program.err', $output_storage_limit),
            'output_system' => rest_encode_file($testcasedir . '/system.out', $output_storage_limit),
            'metadata' => rest_encode_file($testcasedir . '/program.meta', $output_storage_limit),
            'output_diff'  => rest_encode_file($testcasedir . '/feedback/judgemessage.txt', $output_storage_limit)
        );
        $unsent_judging_runs[] = $new_judging_run;
        $outstanding_data += strlen(var_export($new_judging_run, TRUE));

        $now = now();
        if (!$lastcase_correct
            || ($now - $last_sent) >= $update_every_X_seconds
            || $outstanding_data > $row['outputlimit'] * 1024) {
           if (send_unsent_judging_runs($unsent_judging_runs, $row['judgingid']) === null) {
               disable('problem', 'probid', $row['probid'], "uploading unsent judging runs failed", $row['judgingid'], (string)$row['cid']);
               return;
           }
           $unsent_judging_runs = array();
           $last_sent = $now;
           $outstanding_data = 0;
        }
        logmsg(LOG_DEBUG, "Testcase $tc[rank] done, result: " . $result);
    } // end: for each testcase
    if (!empty($unsent_judging_runs)) {
        if (send_unsent_judging_runs($unsent_judging_runs, $row['judgingid']) === null) {
            disable('problem', 'probid', $row['probid'], "uploading unsent judging runs failed", $row['judgingid'], (string)$row['cid']);
            return;
        }
    }

    // revoke readablity for domjudge-run user to this workdir
    chmod($workdir, 0700);

    // destroy chroot environment
    logmsg(LOG_INFO, "executing chroot script: '".CHROOT_SCRIPT." stop'");
    system(LIBJUDGEDIR.'/'.CHROOT_SCRIPT.' stop', $retval);
    if ($retval!=0) {
        error("chroot script exited with exitcode $retval");
    }

    // Evict all contents of the workdir from the kernel fs cache
    system(LIBJUDGEDIR . "/evict $workdir", $retval);
    if ($retval!=0) {
        warning("evict script exited with exitcode $retval");
    }

    // Sanity check: need to have had at least one testcase
    if ($totalcases == 0) {
        logmsg(LOG_WARNING, "No testcases judged for s$row[submitid]/j$row[judgingid]!");
    }

    // done!
    logmsg(LOG_NOTICE, "Judging s$row[submitid]/j$row[judgingid] finished");
}

/**
 */
function fetchTestcase(array $row, $workdirpath, $rank): array
{
    // Get both in- and output files, only if we didn't have them already.
    $tcfile = array();
    $fetched = array();
    $tc = $row['testcases'][$rank];
    foreach (array('input', 'output') as $inout) {
        $tcfile[$inout] = "$workdirpath/testcase/testcase.$row[probid].$rank." .
            $tc['md5sum_' . $inout] . "." . substr($inout, 0, -3);

        if (!file_exists($tcfile[$inout])) {
            $url = sprintf('testcases/%s/file/%s', $tc['testcaseid'], $inout);
            $content = request($url, 'GET', '', FALSE);
            if ($content === NULL) {
                $error = 'Download of ' . $inout . ' failed for case ' . $tc['testcaseid'] . ', check your problem integrity.';
                logmsg(LOG_ERR, $error);
                disable('problem', 'probid', $row['probid'], $error, $row['judgingid'], (string)$row['cid']);
                return NULL;
            }
            $content = base64_decode(dj_json_decode($content));
            if (file_put_contents($tcfile[$inout] . ".new", $content) === false) {
                error("Could not create $tcfile[$inout].new");
            }
            unset($content);
            if (md5_file("$tcfile[$inout].new") === $tc['md5sum_' . $inout]) {
                rename("$tcfile[$inout].new", $tcfile[$inout]);
            } else {
                error("File corrupted during download.");
            }
            $fetched[] = $inout;
        }
        // sanity check (NOTE: performance impact is negligible with 5
        // testcases and total 3.3 MB of data)
        if (md5_file($tcfile[$inout]) !== $tc['md5sum_' . $inout]) {
            error("File corrupted: md5sum mismatch: " . $tcfile[$inout]);
        }
    }
    // Only log downloading input and/or output testdata once.
    if (count($fetched) > 0) {
        logmsg(LOG_INFO, "Fetched new " . implode(',', $fetched) .
            " testcase $rank for problem p$row[probid]");
    }
    return $tcfile;
}
