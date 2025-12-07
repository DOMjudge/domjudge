<?php declare(strict_types=1);
/**
 * Requests a batch of judge tasks the domserver, executes them and reports
 * the results back to the domserver.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

namespace DOMjudge;

if (isset($_SERVER['REMOTE_ADDR'])) {
    die("Commandline use only");
}

require(ETCDIR . '/judgehost-config.php');
require(LIBDIR . '/lib.misc.php');

define('DONT_CARE', new class {});

class JudgeDaemon
{
    private static ?JudgeDaemon $instance = null;

    private ?array $endpoint = null;
    private array $domjudge_config = [];
    private string $myhost;
    private int $verbose = LOG_INFO;
    private ?string $daemonid = null;
    private array $options = [];

    private bool $exitsignalled = false;
    private bool $gracefulexitsignalled = false;

    private ?string $lastrequest = '';
    private float $waittime = self::INITIAL_WAITTIME_SEC;

    private array $langexts = [];

    private $lockfile;
    private array $EXITCODES;

    const INITIAL_WAITTIME_SEC = 0.1;
    const MAXIMAL_WAITTIME_SEC = 5.0;

    const SCRIPT_ID = 'judgedaemon';
    const CHROOT_SCRIPT = 'chroot-startstop.sh';

    public static function signalHandler(int $signal): void
    {
        if (self::$instance) {
            self::$instance->handleSignal($signal);
        }
    }

    public function handleSignal(int $signal): void
    {
        logmsg(LOG_NOTICE, "Signal $signal received.");
        if ($signal === SIGHUP) {
            logmsg(LOG_NOTICE, "SIGHUP received, restarting.");
            $this->exitsignalled = true;
        } elseif ($signal === SIGUSR1) {
            $this->gracefulexitsignalled = true;
            logmsg(LOG_NOTICE, "SIGUSR1 received, finishing current judging and exiting.");
        } else {
            $this->exitsignalled = true;
            logmsg(LOG_NOTICE, "Received signal, exiting.");
        }
    }

    public function __construct()
    {
        self::$instance = $this;

        $this->options = getopt("dv:n:hV", ["diskspace-error"]);
        if ($this->options === false) {
            echo "Error: parsing options failed.\n";
            $this->usage();
        }
        if (isset($this->options['v'])) {
            $this->options['verbose'] = $this->options['v'];
        }
        if (isset($this->options['n'])) {
            $this->options['daemonid'] = $this->options['n'];
        }

        if (isset($this->options['V'])) {
            $this->version();
        }
        if (isset($this->options['h'])) {
            $this->usage();
        }

        if (posix_getuid() == 0 || posix_geteuid() == 0) {
            echo "This program should not be run as root.\n";
            exit(1);
        }

        $hostname = gethostname();
        if ($hostname === false) {
            error("Could not determine hostname.");
        }
        $this->myhost = explode('.', $hostname)[0];
        if (isset($this->options['daemonid'])) {
            if (preg_match('/^\d+$/', $this->options['daemonid'])) {
                $this->myhost = $this->myhost . "-" . $this->options['daemonid'];
                $this->daemonid = $this->options['daemonid'];
            } else {
                echo "Invalid value for daemonid, must be positive integer.\n";
                exit(1);
            }
        }

        define('LOGFILE', LOGDIR . '/judge.' . $this->myhost . '.log');
        // We can only load this here after defining the LOGFILE.
        require(LIBDIR . '/lib.error.php');

        if (isset($this->options['verbose'])) {
            if (preg_match('/^\d+$/', $this->options['verbose'])) {
                $this->verbose = (int)$this->options['verbose'];
                if ($this->verbose >= LOG_DEBUG) {
                    // Also enable judging scripts debug output
                    putenv('DEBUG=1');
                }
            } else {
                error("Invalid value for verbose, must be positive integer.");
            }
        }

        global $verbose;
        $verbose = $this->verbose;

        $runuser = RUNUSER;
        if (isset($this->options['daemonid'])) {
            $runuser .= '-' . $this->options['daemonid'];
        }

        if ($runuser === posix_getpwuid(posix_geteuid())['name'] ||
            RUNGROUP === posix_getgrgid(posix_getegid())['name']
        ) {
            error("Do not run the judgedaemon as the runuser or rungroup.");
        }

        // Set static environment variables for passing path configuration
        // to called programs:
        putenv('DJ_BINDIR=' . BINDIR);
        putenv('DJ_ETCDIR=' . ETCDIR);
        putenv('DJ_JUDGEDIR=' . JUDGEDIR);
        putenv('DJ_LIBDIR=' . LIBDIR);
        putenv('DJ_LIBJUDGEDIR=' . LIBJUDGEDIR);
        putenv('DJ_LOGDIR=' . LOGDIR);
        putenv('RUNUSER=' . $runuser);
        putenv('RUNGROUP=' . RUNGROUP);

        global $EXITCODES;
        $this->EXITCODES = $EXITCODES;
        foreach ($this->EXITCODES as $code => $name) {
            $var = 'E_' . strtoupper(str_replace('-', '_', $name));
            putenv($var . '=' . $code);
        }

        // Pass SYSLOG variable via environment for compare program
        if (defined('SYSLOG') && SYSLOG) {
            putenv('DJ_SYSLOG=' . SYSLOG);
        }

        // The judgedaemon calls itself to send judging results back to the API
        // asynchronously. See the handling of the 'e' option below. The code here
        // should only be run during a normal judgedaemon start.
        if (empty($this->options['e'])) {
            if (!posix_getpwnam($runuser)) {
                error("runuser $runuser does not exist.");
            }

            define('LOCKFILE', RUNDIR . '/judge.' . $this->myhost . '.lock');
            if (($this->lockfile = fopen(LOCKFILE, 'c')) === false) {
                error("cannot open lockfile '" . LOCKFILE . "' for writing");
            }
            if (!flock($this->lockfile, LOCK_EX | LOCK_NB)) {
                error("cannot lock '" . LOCKFILE . "', is another judgedaemon already running?");
            }
            if (!ftruncate($this->lockfile, 0) || fwrite($this->lockfile, (string)getmypid()) === false) {
                error("cannot write PID to '" . LOCKFILE . "'");
            }

            $output = [];
            exec("ps -u '$runuser' -o pid= -o comm=", $output, $retval);
            if (count($output) !== 0) {
                error("found processes still running as '$runuser', check manually:\n" .
                    implode("\n", $output));
            }

            logmsg(LOG_NOTICE, "Judge started on $this->myhost [DOMjudge/" . DOMJUDGE_VERSION . "]");
        }

        $this->initsignals();

        $this->readCredentials();
    }

    public function run(): void
    {
        $this->initialize();

        // Constantly check API for outstanding judgetasks, cycling through all
        // configured endpoints.
        $this->loop();
    }

    private function initialize(): void
    {
        // Set umask to allow group and other access, as this is needed for the
        // unprivileged user.
        umask(0022);

        // Check basic prerequisites for chroot at judgehost startup
        logmsg(LOG_INFO, "🔏 Executing chroot script: '" . self::CHROOT_SCRIPT . " check'");
        if (!$this->runCommandSafe([LIBJUDGEDIR . '/' . self::CHROOT_SCRIPT, 'check'])) {
            error("chroot validation check failed");
        }

        $this->registerJudgehost();

        // Populate the DOMjudge configuration initially
        $this->djconfigRefresh();

        // Prepopulate default language extensions, afterwards update based on
        // domserver config.
        $this->langexts = [
            'c' => ['c'],
            'cpp' => ['cpp', 'C', 'cc'],
            'java' => ['java'],
            'py' => ['py'],
        ];
        $domserver_languages = dj_json_decode($this->request('languages', 'GET'));
        foreach ($domserver_languages as $language) {
            $id = $language['id'];
            if (key_exists($id, $this->langexts)) {
                $this->langexts[$id] = $language['extensions'];
            }
        }
    }

    private function loop(): void
    {
        $lastWorkdir = null;
        $workdirpath = JUDGEDIR . "/$this->myhost/endpoint-" . $this->endpoint['id'];

        while (true) {
            if ($this->endpoint['errorred']) {
                $this->registerJudgehost();
            }

            if ($this->endpoint['waiting']) {
                dj_sleep($this->waittime);
                $this->waittime = min($this->waittime * 2, self::MAXIMAL_WAITTIME_SEC);
            } else {
                $this->waittime = self::INITIAL_WAITTIME_SEC;
            }

            // Check whether we have received an exit signal
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if (function_exists('pcntl_waitpid')) {
                // Reap any finished child processes.
                while (true) {
                    $ret = pcntl_waitpid(-1, $status, WNOHANG);
                    if ($ret <= 0) {
                        if ($ret < 0) {
                            $errno = pcntl_get_last_error();
                            // 10 is ECHLD (not defined in PHP unfortunately),
                            // indicating that we didn't find any child to be
                            // reaped
                            if ($errno != 10) {
                                logmsg(
                                    LOG_WARNING,
                                    "pcntl_waitpid returned $ret when trying to reap child processes: "
                                    . pcntl_strerror($errno)
                                );
                            }
                        }

                        break;
                    }
                }
            }
            if ($this->exitsignalled) {
                logmsg(LOG_NOTICE, "Received signal, exiting.");
                $this->closeCurlHandles();
                fclose($this->lockfile);
                exit;
            }

            if ($this->endpoint['errorred']) {
                continue;
            }


            if ($this->endpoint['waiting'] === false) {
                $this->checkDiskSpace($workdirpath);
            }

            // Request open judge tasks to be executed.
            // Any errors will be treated as non-fatal: we will just keep on retrying in this loop.
            $row = $this->fetchWork();

            // If $row is null, an error occurred; we marked the endpoint already as errorred above.
            if (is_null($row)) {
                continue;
            } else {
                $row = dj_json_decode($row);
            }

            // Nothing returned -> no open work for us.
            if (empty($row)) {
                if (!$this->endpoint["waiting"]) {
                    $this->endpoint["waiting"] = true;
                    if ($lastWorkdir !== null) {
                        $this->cleanupJudging($lastWorkdir);
                        $lastWorkdir = null;
                    }
                    logmsg(LOG_INFO, "No submissions in queue (for endpoint " . $this->endpoint['id'] . "), waiting...");
                    $judgehosts = $this->request('judgehosts', 'GET');
                    if ($judgehosts !== null) {
                        $judgehosts = dj_json_decode($judgehosts);
                        $judgehost = array_values(array_filter($judgehosts, fn ($j) => $j['hostname'] === $this->myhost))[0];
                        if (!isset($judgehost['enabled']) || !$judgehost['enabled']) {
                            logmsg(LOG_WARNING, "Judgehost needs to be enabled in web interface.");
                        }
                    }
                }
                continue;
            }

            // We have gotten a work packet.
            $this->endpoint["waiting"] = false;

            // All tasks are guaranteed to be of the same type.
            // If $row is empty, we already continued.
            // If $row[0] is not set, or $row[0]['type'] is not set, something is wrong.
            if (!isset($row[0]['type'])) {
                logmsg(LOG_ERR, "Received work packet with invalid format: 'type' not found in first element.");
                continue;
            }
            $type = $row[0]['type'];

            $this->handleTask($type, $row, $lastWorkdir, $workdirpath);
        }
    }

    private function handleJudgingTask(array $row, ?string &$lastWorkdir, string $workdirpath, string $workdir): void
    {
        $success_file = "$workdir/.uuid_pid";
        $expected_uuid_pid = $row[0]['uuid'] . '_' . (string)getmypid();

        $needs_cleanup = false;
        if ($lastWorkdir !== $workdir) {
            // Switching between workdirs requires cleanup.
            $needs_cleanup = true;
        }
        if (file_exists($workdir)) {
            // If the workdir still exists we need to check whether it may be a left-over from a previous database.
            // If that is the case, we need to rename it and potentially clean up.
            if (file_exists($success_file)) {
                $old_uuid_pid = file_get_contents($success_file);
                if ($old_uuid_pid !== $expected_uuid_pid) {
                    $needs_cleanup = true;
                    unlink($success_file);
                }
            } else {
                $old_uuid_pid = 'n/a';
                $needs_cleanup = true;
            }

            // Either the file didn't exist or we deleted it above.
            if (!file_exists($success_file)) {
                $oldworkdir = $workdir . '-old-' . getmypid() . '-' . date('Y-m-d_H:i');
                if (!rename($workdir, $oldworkdir)) {
                    error("Could not rename stale working directory to '$oldworkdir'.");
                }
                @chmod($oldworkdir, 0700);
                warning("Found stale working directory; renamed to '$oldworkdir'.");
            }
        }

        if ($needs_cleanup && $lastWorkdir !== null) {
            $this->cleanupJudging($lastWorkdir);
            $lastWorkdir = null;
        }


        if (!$this->runCommandSafe(['mkdir', '-p', "$workdir/compile"])) {
            error("Could not create '$workdir/compile'");
        }

        chmod($workdir, 0755);

        if (!chdir($workdir)) {
            error("Could not chdir to '$workdir'");
        }

        if ($lastWorkdir !== $workdir) {
            // create chroot environment
            logmsg(LOG_INFO, "  🔒 Executing chroot script: '" . self::CHROOT_SCRIPT . " start'");
            if (!$this->runCommandSafe([LIBJUDGEDIR . '/' . self::CHROOT_SCRIPT, 'start'], $retval)) {
                logmsg(LOG_ERR, "chroot script exited with exitcode $retval");
                $this->disable('judgehost', 'hostname', $this->myhost, "chroot script exited with exitcode $retval on $this->myhost");
                return;
            }

            // Refresh config at start of each batch.
            $this->djconfigRefresh();

            $lastWorkdir = $workdir;
        }

        // Make sure the workdir is accessible for the domjudge-run user.
        // Will be revoked again after this run finished.
        foreach ($row as $judgetask) {
            if (!$this->compileAndRunSubmission($judgetask, $workdirpath)) {
                // Potentially return remaining outstanding judgetasks here.
                $returnedJudgings = $this->request('judgehosts', 'POST', ['hostname' => urlencode($this->myhost)], false);
                if ($returnedJudgings !== null) {
                    $returnedJudgings = dj_json_decode($returnedJudgings);
                    foreach ($returnedJudgings as $jud) {
                        $workdir = $this->judgingDirectory($workdirpath, $jud);
                        @chmod($workdir, 0700);
                        logmsg(LOG_WARNING, "  🔙 Returned unfinished judging with jobid " . $jud['jobid'] .
                            " in my name; given back unfinished runs from me.");
                    }
                }
                break;
            }
        }

        file_put_contents($success_file, $expected_uuid_pid);

        // Check if we were interrupted while judging, if so, exit (to avoid sleeping)
        if ($this->exitsignalled) {
            logmsg(LOG_NOTICE, "Received signal, exiting.");
            $this->closeCurlHandles();
            fclose($this->lockfile);
            exit;
        }
    }

    private function handleDebugInfoTask(array $row, ?string &$lastWorkdir, string $workdirpath, string $workdir): void
    {
        if ($lastWorkdir !== null) {
            $this->cleanupJudging($lastWorkdir);
            $lastWorkdir = null;
        }
        foreach ($row as $judgeTask) {
            if (isset($judgeTask['run_script_id'])) {
                // Full debug package requested.
                $run_config = dj_json_decode($judgeTask['run_config']);
                $tmpfile = tempnam(TMPDIR, 'full_debug_package_');
                [$runpath, $error] = $this->fetchExecutable(
                    $workdirpath,
                    'debug',
                    $judgeTask['run_script_id'],
                    $run_config['hash'],
                    $judgeTask['judgetaskid']
                );

                if (!$this->runCommandSafe([$runpath, $workdir, $tmpfile])) {
                    $this->disable('run_script', 'run_script_id', $judgeTask['run_script_id'], "Running '$runpath' failed.");
                }

                $this->request(
                    sprintf(
                        'judgehosts/add-debug-info/%s/%s',
                        urlencode($this->myhost),
                        urlencode((string)$judgeTask['judgetaskid'])
                    ),
                    'POST',
                    ['full_debug' => $this->restEncodeFile($tmpfile, false)],
                    false
                );
                unlink($tmpfile);

                logmsg(LOG_INFO, "  ⇡ Uploading debug package of workdir $workdir.");
            } else {
                // Retrieving full team output for a particular testcase.
                $testcasedir = $workdir . "/testcase" . sprintf('%05d', $judgeTask['testcase_id']);
                $this->request(
                    sprintf(
                        'judgehosts/add-debug-info/%s/%s',
                        urlencode($this->myhost),
                        urlencode((string)$judgeTask['judgetaskid'])
                    ),
                    'POST',
                    ['output_run' => $this->restEncodeFile($testcasedir . '/program.out', false)],
                    false
                );
                logmsg(LOG_INFO, "  ⇡ Uploading full output of testcase $judgeTask[testcase_id].");
            }
        }
    }

    private function handlePrefetchTask(array $row, ?string &$lastWorkdir, string $workdirpath): void
    {
        if ($lastWorkdir !== null) {
            $this->cleanupJudging($lastWorkdir);
            $lastWorkdir = null;
        }
        foreach ($row as $judgeTask) {
            foreach (['compile', 'run', 'compare'] as $script_type) {
                if (!empty($judgeTask[$script_type . '_script_id']) && !empty($judgeTask[$script_type . '_config'])) {
                    $config = dj_json_decode($judgeTask[$script_type . '_config']);
                    $combined_run_compare = $script_type == 'run' && ($config['combined_run_compare'] ?? false);
                    if (!empty($config['hash'])) {
                        [$execrunpath, $error] = $this->fetchExecutable(
                            $workdirpath,
                            $script_type,
                            $judgeTask[$script_type . '_script_id'],
                            $config['hash'],
                            $judgeTask['judgetaskid'],
                            $combined_run_compare
                        );
                    }
                }
            }
            if (!empty($judgeTask['testcase_id'])) {
                $this->fetchTestcase($workdirpath, $judgeTask['testcase_id'], $judgeTask['judgetaskid'], $judgeTask['testcase_hash']);
            }
        }
        logmsg(LOG_INFO, "  🔥 Pre-heating judgehost completed.");
    }

    private function handleTask(string $type, array $row, ?string &$lastWorkdir, string $workdirpath): void
    {
        if ($type == 'try_again') {
            if (!$this->endpoint['retrying']) {
                logmsg(LOG_INFO, "API indicated to retry fetching work (this might take a while to clean up).");
            }
            $this->endpoint['retrying'] = true;
            return;
        }
        $this->endpoint['retrying'] = false;

        logmsg(
            LOG_INFO,
            "⇝ Received " . sizeof($row) . " '" . $type . "' judge tasks (endpoint " . $this->endpoint['id'] . ")"
        );

        if ($type == 'prefetch') {
            $this->handlePrefetchTask($row, $lastWorkdir, $workdirpath);
            return;
        }

        if ($type == 'debug_info') {
            // Create workdir for debugging only if needed.
            $workdir = $this->judgingDirectory($workdirpath, $row[0]);
            logmsg(LOG_INFO, "  Working directory: $workdir");

            $this->handleDebugInfoTask($row, $lastWorkdir, $workdirpath, $workdir);
            return;
        }

        // Create workdir for judging.
        $workdir = $this->judgingDirectory($workdirpath, $row[0]);
        logmsg(LOG_INFO, "  Working directory: $workdir");
        $this->handleJudgingTask($row, $lastWorkdir, $workdirpath, $workdir);
    }

    private function fetchWork()
    {
        return $this->request('judgehosts/fetch-work', 'POST', ['hostname' => $this->myhost], false);
    }

    private function checkDiskSpace(string $workdirpath): void
    {
        // Check for available disk space
        $free_space = disk_free_space(JUDGEDIR);
        $allowed_free_space = $this->djconfigGetValue('diskspace_error'); // in kB
        if ($free_space < 1024 * $allowed_free_space) {
            $after = disk_free_space(JUDGEDIR);
            if (!isset($this->options['diskspace-error'])) {
                $candidateDirs = [];
                foreach (scandir($workdirpath) as $subdir) {
                    if (is_numeric($subdir) && is_dir(($workdirpath . "/" . $subdir))) {
                        $candidateDirs[] = $workdirpath . "/" . $subdir;
                    }
                }
                uasort($candidateDirs, fn ($a, $b) => filemtime($a) <=> filemtime($b));
                $after = $before = disk_free_space(JUDGEDIR);
                logmsg(
                    LOG_INFO,
                    "🗑 Low on diskspace, cleaning up (" . count($candidateDirs) . " potential candidates)."
                );
                $cnt = 0;
                foreach ($candidateDirs as $d) {
                    $cnt++;
                    logmsg(LOG_INFO, "  - deleting $d");
                    if (!$this->runCommandSafe(['rm', '-rf', $d])) {
                        logmsg(LOG_WARNING, "Deleting '$d' was unsuccessful.");
                    }
                    $after = disk_free_space(JUDGEDIR);
                    if ($after >= 1024 * $allowed_free_space) {
                        break;
                    }
                }
                logmsg(
                    LOG_INFO,
                    "🗑 Cleaned up $cnt old judging directories; reduced disk space by " .
                    sprintf("%01.2fMB.", ($after - $before) / (1024 * 1024))
                );
            }
            if ($after < 1024 * $allowed_free_space) {
                $free_abs = sprintf("%01.2fGB", $after / (1024 * 1024 * 1024));
                logmsg(LOG_ERR, "Low on disk space: $free_abs free, clean up or " .
                    "change 'diskspace error' value in config before resolving this error.");

                $this->disable('judgehost', 'hostname', $this->myhost, "low on disk space on $this->myhost");
            }
        }
    }

    private function judgingDirectory(string $workdirpath, array $judgeTask): string
    {
        if (filter_var($judgeTask['submitid'], FILTER_VALIDATE_INT) === false ||
            filter_var($judgeTask['jobid'], FILTER_VALIDATE_INT) === false) {
            error("Malformed data returned in judgeTask IDs: " . var_export($judgeTask, true));
        }

        return $workdirpath . '/'
            . $judgeTask['submitid'] . '/'
            . $judgeTask['jobid'];
    }

    private function readCredentials(): void
    {
        $credfile = ETCDIR . '/restapi.secret';
        if (!is_readable($credfile)) {
            error("REST API credentials file " . $credfile . " is not readable or does not exist.");
        }
        $credentials = file($credfile);
        if ($credentials === false) {
            error("Error reading REST API credentials file " . $credfile);
        }
        $lineno = 0;
        foreach ($credentials as $credential) {
            ++$lineno;
            $credential = trim($credential);
            if ($credential === '' || $credential[0] === '#') {
                continue;
            }
            /** @var string[] $items */
            $items = preg_split("/\s+/", $credential);
            if (count($items) !== 4) {
                error("Error parsing REST API credentials. Invalid format in line $lineno.");
            }
            [$endpointID, $resturl, $restuser, $restpass] = $items;

            if ($this->endpoint !== null) {
                error("Error parsing REST API credentials. Multiple endpoints are not supported.");
            }
            $this->endpoint = [
                "id" => $endpointID,
                "url" => $resturl,
                "user" => $restuser,
                "pass" => $restpass,
                "waiting" => false,
                "errorred" => false,
                "last_attempt" => -1,
                "retrying" => false,
            ];
        }
        if ($this->endpoint === null) {
            error("Error parsing REST API credentials: no endpoints found.");
        }
    }

    private function setupCurlHandle(string $restuser, string $restpass): \CurlHandle|false
    {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_USERAGENT, "DOMjudge/" . DOMJUDGE_VERSION);
        curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_handle, CURLOPT_USERPWD, $restuser . ":" . $restpass);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        return $curl_handle;
    }

    private function closeCurlHandles(): void
    {
        if (!empty($this->endpoint['ch'])) {
            curl_close($this->endpoint['ch']);
            unset($this->endpoint['ch']);
        }
    }

    private function request(string $url, string $verb = 'GET', $data = '', bool $failonerror = true)
    {
        // Don't flood the log with requests for new judgings every few seconds.
        if (str_starts_with($url, 'judgehosts/fetch-work') && $verb === 'POST') {
            if ($this->lastrequest !== $url) {
                logmsg(LOG_DEBUG, "API request $verb $url");
                $this->lastrequest = $url;
            }
        } else {
            logmsg(LOG_DEBUG, "API request $verb $url");
            $this->lastrequest = $url;
        }

        $requestUrl = $this->endpoint['url'] . "/" . $url;
        $curl_handle = $this->endpoint['ch'];
        if ($verb == 'GET') {
            $requestUrl .= '?' . $data;
        }

        curl_setopt($curl_handle, CURLOPT_URL, $requestUrl);

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

        $delay_in_sec = BACKOFF_INITIAL_DELAY_SEC;
        $succeeded = false;
        $response = null;
        $errstr = null;

        for ($trial = 1; $trial <= BACKOFF_STEPS; $trial++) {
            $response = curl_exec($curl_handle);
            if ($response === false) {
                $errstr = "Error while executing curl $verb to url " . $requestUrl . ": " . curl_error($curl_handle);
            } else {
                $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
                if ($status == 401) {
                    $errstr = "Authentication failed (error $status) while contacting $requestUrl. " .
                        "Check credentials in restapi.secret.";
                    // Do not retry on authentication failures.
                    break;
                } elseif ($status < 200 || $status >= 300) {
                    $json = dj_json_try_decode($response);
                    if ($json !== null) {
                        $response = var_export($json, true);
                    }
                    $errstr = "Error while executing curl $verb to url " . $requestUrl .
                        ": http status code: " . $status .
                        ", request size = " . strlen(print_r($data, true)) .
                        ", response: " . $response;
                } else {
                    $succeeded = true;
                    break;
                }
            }
            if ($trial == BACKOFF_STEPS) {
                $errstr = $errstr . " Retry limit reached.";
            } else {
                $retry_in_sec = $delay_in_sec + BACKOFF_JITTER_SEC * random_int(0, mt_getrandmax()) / mt_getrandmax();
                $warnstr = $errstr . " This request will be retried after about " .
                    round($retry_in_sec, 2) . "sec... (" . $trial . "/" . BACKOFF_STEPS . ")";
                warning($warnstr);
                dj_sleep($retry_in_sec);
                $delay_in_sec = $delay_in_sec * BACKOFF_FACTOR;
            }
        }
        if (!$succeeded) {
            if ($failonerror) {
                error($errstr);
            } else {
                warning($errstr);
                $this->endpoint['errorred'] = true;
                return null;
            }
        }

        if ($this->endpoint['errorred']) {
            $this->endpoint['errorred'] = false;
            $this->endpoint['waiting'] = false;
            logmsg(LOG_NOTICE, "Reconnected to endpoint " . $this->endpoint['id'] . ".");
        }

        return $response;
    }

    private function djconfigRefresh(): void
    {
        $res = $this->request('config', 'GET');
        $res = dj_json_decode($res);
        $this->domjudge_config = $res;
    }

    private function djconfigGetValue(string $name)
    {
        if (empty($this->domjudge_config)) {
            $this->djconfigRefresh();
        }

        if (!array_key_exists($name, $this->domjudge_config)) {
            error("Configuration value '$name' not found in config.");
        }
        return $this->domjudge_config[$name];
    }

    private function restEncodeFile(string $file, $sizelimit = true): string
    {
        $maxsize = null;
        if ($sizelimit === true) {
            $maxsize = (int)$this->djconfigGetValue('output_storage_limit');
        } elseif ($sizelimit === false || $sizelimit == -1) {
            $maxsize = -1;
        } elseif (is_int($sizelimit) && $sizelimit > 0) {
            $maxsize = $sizelimit;
        } else {
            error("Invalid argument sizelimit = '$sizelimit' specified.");
        }
        return base64_encode(dj_file_get_contents($file, $maxsize));
    }

    private function usage(): never
    {
        echo "Usage: " . self::SCRIPT_ID . " [OPTION]...\n" .
            "Start the judgedaemon.\n\n" .
            "  -n <id>           bind to CPU <id> and user " . RUNUSER . "-<id>\n" .
            "  --diskspace-error send internal error on low diskspace; if not set,\n" .
            "                      the judgedaemon will try to clean up and continue\n" .
            "  -v <level>        set verbosity to <level>; these are syslog levels:\n" .
            "                      default is LOG_INFO = 6, max is LOG_DEBUG = 7\n" .
            "  -h                display this help and exit\n" .
            "  -V                output version information and exit\n\n";
        exit;
    }

    private function version(): never
    {
        echo self::SCRIPT_ID . " for DOMjudge version " . DOMJUDGE_VERSION . "\n";
        echo "Written by the DOMjudge developers\n\n";
        echo "DOMjudge comes with ABSOLUTELY NO WARRANTY.  This is free software, and you\n";
        echo "are welcome to redistribute it under certain conditions.  See the GNU\n";
        echo "General Public Licence for details.\n";
        exit;
    }

    private function readJudgehostLog(int $numLines = 20): string
    {
        ob_start();
        passthru("tail -n $numLines " . dj_escapeshellarg(LOGFILE));
        return trim(ob_get_clean());
    }

    private function runCommandSafe(array $command_parts, &$retval = DONT_CARE, $log_nonzero_exitcode = true): bool
    {
        if (empty($command_parts)) {
            logmsg(LOG_WARNING, "Need at least the command that should be called.");
            $retval = -1;
            return false;
        }

        $command = implode(' ', array_map('dj_escapeshellarg', $command_parts));

        logmsg(LOG_DEBUG, "Executing command: $command");
        system($command, $retval_local);
        if ($retval !== DONT_CARE) {
            $retval = $retval_local;
        } // phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed

        if ($retval_local !== 0) {
            if ($log_nonzero_exitcode) {
                logmsg(LOG_WARNING, "Command failed with exit code $retval_local: $command");
            }
            return false;
        }

        return true;
    }

    private function fetchExecutable(
        string $workdirpath,
        string $type,
        string $execid,
        string $hash,
        int    $judgeTaskId,
        bool   $combined_run_compare = false
    ): array {
        [$execrunpath, $error, $buildlogpath] = $this->fetchExecutableInternal($workdirpath, $type, $execid, $hash, $combined_run_compare);
        if (isset($error)) {
            $extra_log = null;
            if ($buildlogpath !== null) {
                $extra_log = dj_file_get_contents($buildlogpath, 4096);
            }
            logmsg(
                LOG_ERR,
                "Fetching executable failed for $type script '$execid': " . $error
            );
            $description = "$execid: fetch, compile, or deploy of $type script failed.";
            $this->disable(
                $type . '_script',
                $type . '_script_id',
                $execid,
                $description,
                $judgeTaskId,
                $extra_log
            );
        }
        return [$execrunpath, $error];
    }

    private function fetchExecutableInternal(
        string $workdirpath,
        string $type,
        string $execid,
        string $hash,
        bool   $combined_run_compare = false
    ): array {
        $execdir = join('/', [
            $workdirpath,
            'executable',
            $type,
            $execid,
            $hash
        ]);
        $execdeploypath = $execdir . '/.deployed';
        $execbuilddir = $execdir . '/build';
        $execbuildpath = $execbuilddir . '/build';
        $execrunpath = $execbuilddir . '/run';
        $execrunjurypath = $execbuilddir . '/runjury';
        if (!is_dir($execdir) || !file_exists($execdeploypath) ||
            ($combined_run_compare && file_get_contents(LIBJUDGEDIR . '/run-interactive.sh') !== file_get_contents($execrunpath))) {
            if (!$this->runCommandSafe(['rm', '-rf', $execdir, $execbuilddir])) {
                $this->disable('judgehost', 'hostname', $this->myhost, "Deleting '$execdir' or '$execbuilddir' was unsuccessful.");
            }
            if (!$this->runCommandSafe(['mkdir', '-p', $execbuilddir])) {
                $this->disable('judgehost', 'hostname', $this->myhost, "Could not create directory '$execbuilddir'");
            }

            logmsg(LOG_INFO, "  💾 Fetching new executable '$type/$execid' with hash '$hash'.");
            $content = $this->request(sprintf('judgehosts/get_files/%s/%s', $type, $execid), 'GET');
            $files = dj_json_decode($content);
            unset($content);
            $filesArray = [];
            foreach ($files as $file) {
                $filename = $execbuilddir . '/' . $file['filename'];
                $content = base64_decode($file['content']);
                file_put_contents($filename, $content);
                if ($file['is_executable']) {
                    chmod($filename, 0755);
                }
                $filesArray[] = [
                    'hash' => md5($content),
                    'filename' => $file['filename'],
                    'is_executable' => $file['is_executable'],
                ];
            }
            unset($files);
            uasort($filesArray, fn (array $a, array $b) => strcmp($a['filename'], $b['filename']));
            $computedHash = md5(
                join(
                    array_map(
                        fn ($file) => $file['hash'] . $file['filename'] . $file['is_executable'],
                        $filesArray
                    )
                )
            );
            if ($hash !== $computedHash) {
                return [null, "Unexpected hash ($computedHash), expected hash: $hash", null];
            }

            $do_compile = true;
            if (!file_exists($execbuildpath)) {
                if (file_exists($execrunpath)) {
                    // 'run' already exists, 'build' does not => don't compile anything
                    logmsg(LOG_DEBUG, "'run' exists without 'build', we are done.");
                    $do_compile = false;
                } else {
                    // detect lang and write build file
                    $buildscript = "#!/bin/sh\n\n";
                    $execlang = false;
                    $source = "";
                    $unescapedSource = "";
                    foreach ($this->langexts as $lang => $langext) {
                        if (($handle = opendir($execbuilddir)) === false) {
                            $this->disable('judgehost', 'hostname', $this->myhost, "Could not open $execbuilddir");
                        }
                        while (($file = readdir($handle)) !== false) {
                            $ext = pathinfo($file, PATHINFO_EXTENSION);
                            if (in_array($ext, $langext)) {
                                $execlang = $lang;
                                $unescapedSource = $file;
                                $source = dj_escapeshellarg($unescapedSource);
                                break;
                            }
                        }
                        closedir($handle);
                        if ($execlang !== false) {
                            break;
                        }
                    }
                    if ($execlang === false) {
                        return [null, "executable must either provide an executable file named 'build' or a C/C++/Java or Python file.", null];
                    }
                    switch ($execlang) {
                        case 'c':
                            $buildscript .= "gcc -Wall -O2 -std=gnu11 $source -o run -lm\n";
                            break;
                        case 'cpp':
                            $buildscript .= "g++ -Wall -O2 -std=gnu++20 $source -o run\n";
                            break;
                        case 'java':
                            $buildscript .= "javac -cp . -d . $source\n";
                            $buildscript .= "echo '#!/bin/sh' > run\n";
                            // no main class detection here
                            $buildscript .= "echo 'COMPARE_DIR=\$(dirname \"\$0\")' >> run\n";
                            $mainClass = basename($unescapedSource, '.java');
                            // Note: since the $@ is within single quotes, we do not need to double escape it.
                            $buildscript .= "echo 'java -cp \"\$COMPARE_DIR\" $mainClass \"\$@\"' >> run\n";
                            $buildscript .= "chmod +x run\n";
                            break;
                        case 'py':
                            $buildscript .= "echo '#!/bin/sh' > run\n";
                            $buildscript .= "echo 'COMPARE_DIR=\$(dirname \"\$0\")' >> run\n";
                            // Note: since the $@ is within single quotes, we do not need to double escape it.
                            $buildscript .= "echo 'python3 \"\$COMPARE_DIR/$source\" \"\$@\"' >> run\n";
                            $buildscript .= "chmod +x run\n";
                            break;
                    }
                    if (file_put_contents($execbuildpath, $buildscript) === false) {
                        $this->disable('judgehost', 'hostname', $this->myhost, "Could not write file 'build' in $execbuilddir");
                    }
                    chmod($execbuildpath, 0755);
                }
            } elseif (!is_executable($execbuildpath)) {
                return [null, "Invalid executable, file 'build' exists but is not executable.", null];
            }

            if ($do_compile) {
                logmsg(LOG_DEBUG, "Building executable in $execdir, under 'build/'");

                putenv('SCRIPTTIMELIMIT=' . $this->djconfigGetValue('script_timelimit'));
                putenv('SCRIPTMEMLIMIT=' . $this->djconfigGetValue('script_memory_limit'));
                putenv('SCRIPTFILELIMIT=' . $this->djconfigGetValue('script_filesize_limit'));

                if (!$this->runCommandSafe([LIBJUDGEDIR . '/build_executable.sh', $execdir])) {
                    return [null, "Failed to build executable in $execdir.", "$execdir/build.log"];
                }
                chmod($execrunpath, 0755);
            }
            if (!is_file($execrunpath) || !is_executable($execrunpath)) {
                return [null, "Invalid build file, must produce an executable file 'run'.", null];
            }
            if ($combined_run_compare) {
                # For combined run and compare (i.e. for interactive problems), we
                # need to wrap the jury provided 'run' script with 'runpipe' to
                # handle the bidirectional communication.  First 'run' is renamed to
                # 'runjury', and then replaced by the script below, which runs the
                # team submission and runjury programs and connects their pipes.
                $runscript = file_get_contents(LIBJUDGEDIR . '/run-interactive.sh');
                if (rename($execrunpath, $execrunjurypath) === false) {
                    $this->disable('judgehost', 'hostname', $this->myhost, "Could not move file 'run' to 'runjury' in $execbuilddir");
                }
                if (file_put_contents($execrunpath, $runscript) === false) {
                    $this->disable('judgehost', 'hostname', $this->myhost, "Could not write file 'run' in $execbuilddir");
                }
                chmod($execrunpath, 0755);
            }

            if (!is_file($execrunpath) || !is_executable($execrunpath)) {
                return [null, "Invalid build file, must produce an executable file 'run'.", null];
            }

            // Create file to mark executable successfully deployed.
            touch($execdeploypath);
        }

        return [$execrunpath, null, null];
    }

    private function registerJudgehost(): void
    {
        $endpoint = &$this->endpoint;

        // Only try to register every 30s.
        $now = time();
        if ($now - $endpoint['last_attempt'] < 30) {
            $endpoint['waiting'] = true;
            return;
        }
        $endpoint['last_attempt'] = $now;

        logmsg(LOG_NOTICE, "Registering judgehost on endpoint " . $this->endpoint['id'] . ": " . $endpoint['url']);
        $this->endpoint['ch'] = $this->setupCurlHandle($endpoint['user'], $endpoint['pass']);

        // Create directory where to test submissions
        $workdirpath = JUDGEDIR . "/$this->myhost/endpoint-" . $this->endpoint['id'];
        if (!$this->runCommandSafe(['mkdir', '-p', "$workdirpath/testcase"])) {
            error("Could not create $workdirpath");
        }
        chmod("$workdirpath/testcase", 0700);

        // Auto-register judgehost.
        // If there are any unfinished judgings in the queue in my name,
        // they have and will not be finished. Give them back.
        $unfinished = $this->request('judgehosts', 'POST', ['hostname' => urlencode($this->myhost)], false);
        if ($unfinished === null) {
            logmsg(LOG_WARNING, "Registering judgehost on endpoint " . $this->endpoint['id'] . " failed.");
        } else {
            $unfinished = dj_json_decode($unfinished);
            foreach ($unfinished as $jud) {
                $workdir = $this->judgingDirectory($workdirpath, $jud);
                @chmod($workdir, 0700);
                logmsg(LOG_WARNING, "Found unfinished judging with jobid " . $jud['jobid'] .
                    " in my name; given back unfinished runs from me.");
            }
        }
    }

    private function disable(
        string  $kind,
        string  $idcolumn,
        mixed   $id,
        string  $description,
        ?int    $judgeTaskId = null,
        ?string $extra_log = null
    ): void {
        $disabled = dj_json_encode(['kind' => $kind, $idcolumn => $id]);
        $judgehostlog = $this->readJudgehostLog();
        if (isset($extra_log)) {
            $judgehostlog .= "\n\n"
                . "--------------------------------------------------------------------------------"
                . "\n\n"
                . $extra_log;
        }
        $args = 'description=' . urlencode($description) .
            '&judgehostlog=' . urlencode(base64_encode($judgehostlog)) .
            '&disabled=' . urlencode($disabled) .
            '&hostname=' . urlencode($this->myhost);
        if (isset($judgeTaskId)) {
            $args .= '&judgetaskid=' . urlencode((string)$judgeTaskId);
        }

        $error_id = $this->request('judgehosts/internal-error', 'POST', $args);
        logmsg(LOG_ERR, "=> internal error " . $error_id);
    }

    private function readMetadata(string $filename): ?array
    {
        if (!is_readable($filename)) {
            return null;
        }

        // Don't quite treat it as YAML, but simply key/value pairs.
        $contents = explode("\n", dj_file_get_contents($filename));
        $res = [];
        foreach ($contents as $line) {
            if (str_contains($line, ":")) {
                [$key, $value] = explode(":", $line, 2);
                $res[$key] = trim($value);
            }
        }

        return $res;
    }

    private function cleanupJudging(string $workdir): void
    {
        // revoke readablity for domjudge-run user to this workdir
        chmod($workdir, 0700);

        // destroy chroot environment
        logmsg(LOG_INFO, "  🔓 Executing chroot script: '" . self::CHROOT_SCRIPT . " stop'");
        if (!$this->runCommandSafe([LIBJUDGEDIR . '/' . self::CHROOT_SCRIPT, 'stop'], $retval)) {
            logmsg(LOG_ERR, "chroot script exited with exitcode $retval");
            $this->disable('judgehost', 'hostname', $this->myhost, "chroot script exited with exitcode $retval on $this->myhost");
            // Just continue here: even though we might continue a current
            // compile/test-run cycle, we don't know whether we're in one here,
            // and worst case, the chroot script will fail the next time when
            // starting.
        }

        // Evict all contents of the workdir from the kernel fs cache
        if (!$this->runCommandSafe([LIBJUDGEDIR . '/evict', $workdir])) {
            warning("evict script failed, continuing gracefully");
        }
    }

    private function compile(
        array   $judgeTask,
        string  $workdir,
        string  $workdirpath,
        array   $compile_config,
        ?string $daemonid,
        int     $output_storage_limit
    ): bool {
        // Reuse compilation if it already exists.
        if (file_exists("$workdir/compile.success")) {
            return true;
        }

        // Verify compile and runner versions.
        $judgeTaskId = $judgeTask['judgetaskid'];
        $version_verification = dj_json_decode($this->request('judgehosts/get_version_commands/' . $judgeTaskId, 'GET'));
        if (isset($version_verification['compiler_version_command']) || isset($version_verification['runner_version_command'])) {
            logmsg(LOG_INFO, "  📋 Verifying versions.");
            $versions = [];
            $version_output_file = $workdir . '/version_check.out';
            $args = 'hostname=' . urlencode($this->myhost);
            foreach (['compiler', 'runner'] as $type) {
                if (isset($version_verification[$type . '_version_command'])) {
                    if (file_exists($version_output_file)) {
                        unlink($version_output_file);
                    }

                    $vcscript_content = $version_verification[$type . '_version_command'];
                    $vcscript = tempnam(TMPDIR, 'version_check-');
                    file_put_contents($vcscript, $vcscript_content);
                    chmod($vcscript, 0755);

                    $this->runCommandSafe([LIBJUDGEDIR . "/version_check.sh", $vcscript, $workdir], $retval);

                    $versions[$type] = trim(file_get_contents($version_output_file));
                    if ($retval !== 0) {
                        $versions[$type] =
                            "Getting $type version failed with exit code $retval\n"
                            . $versions[$type];
                    }

                    unlink($vcscript);
                }
                if (isset($versions[$type])) {
                    $args .= "&$type=" . urlencode(base64_encode($versions[$type]));
                }
            }

            // TODO: Add actual check once implemented in the backend.
            $this->request('judgehosts/check_versions/' . $judgeTaskId, 'PUT', $args);
        }

        // Get the source code from the DB and store in local file(s).
        $url = sprintf('judgehosts/get_files/source/%s', $judgeTask['submitid']);
        $sources = $this->request($url, 'GET');
        $sources = dj_json_decode($sources);
        $files = [];
        $hasFiltered = false;
        foreach ($sources as $source) {
            $srcfile = "$workdir/compile/$source[filename]";
            $file = $source['filename'];
            if ($compile_config['filter_compiler_files']) {
                $picked = false;
                foreach ($compile_config['language_extensions'] as $extension) {
                    $extensionLength = strlen($extension);
                    if (substr($file, -$extensionLength) === $extension) {
                        $files[] = $file;
                        $picked = true;
                        break;
                    }
                }
                if (!$picked) {
                    $hasFiltered = true;
                }
            } else {
                $files[] = $file;
            }
            if (file_put_contents($srcfile, base64_decode($source['content'])) === false) {
                error("Could not create $srcfile");
            }
        }

        if (empty($files) && $hasFiltered) {
            // Note: It may be tempting to assume that this codepath can be never
            // reached since we prevent these submissions from being submitted both
            // via command line and the web interface. However, the code path can
            // be triggered when the filtering is activated between submission and
            // rejudge.
            $message = 'No files with allowed extensions found to pass to compiler. Allowed extensions: '
                . implode(', ', $compile_config['language_extensions']);
            $args = 'compile_success=0' .
                '&output_compile=' . urlencode(base64_encode($message));

            $url = sprintf('judgehosts/update-judging/%s/%s', urlencode($this->myhost), urlencode((string)$judgeTask['judgetaskid']));
            $this->request($url, 'PUT', $args);

            // Revoke readablity for domjudge-run user to this workdir.
            chmod($workdir, 0700);
            logmsg(LOG_NOTICE, "Judging s$judgeTask[submitid], task $judgeTask[judgetaskid]: compile error");
            return false;
        }

        if (count($files) == 0) {
            error("No submission files could be downloaded.");
        }

        [$execrunpath, $error] = $this->fetchExecutable(
            $workdirpath,
            'compile',
            $judgeTask['compile_script_id'],
            $compile_config['hash'],
            $judgeTask['judgetaskid']
        );
        if (isset($error)) {
            return false;
        }

        // Compile the program.
        $compile_command_parts = [LIBJUDGEDIR . '/compile.sh'];
        if (isset($daemonid)) {
            $compile_command_parts[] = '-n';
            $compile_command_parts[] = $daemonid;
        }
        array_push($compile_command_parts, $execrunpath, $workdir, ...$files);
        // Note that the $retval is handled further down after reading/writing metadata.
        $this->runCommandSafe($compile_command_parts, $retval, log_nonzero_exitcode: false);

        $compile_output = '';
        if (is_readable($workdir . '/compile.out')) {
            $compile_output = dj_file_get_contents($workdir . '/compile.out', 50000);
        }
        if (empty($compile_output) && is_readable($workdir . '/compile.tmp')) {
            $compile_output = dj_file_get_contents($workdir . '/compile.tmp', 50000);
        }

        // Try to read metadata from file
        $metadata = $this->readMetadata($workdir . '/compile.meta');
        if (isset($metadata['internal-error'])) {
            alert('error');
            $internalError = $metadata['internal-error'];
            $compile_output .= "\n--------------------------------------------------------------------------------\n\n" .
                "Internal errors reported:\n" . $internalError;

            if (str_starts_with($internalError, 'compile script: ')) {
                $internalError = preg_replace('/^compile script: /', '', $internalError);
                $description = "The compile script returned an error: $internalError";
                $this->disable('compile_script', 'compile_script_id', $judgeTask['compile_script_id'], $description, $judgeTask['judgetaskid'], $compile_output);
            } else {
                $description = "Running compile.sh caused an error/crash: $internalError";
                // Note we are disabling the judgehost in this case since it's
                // likely an error intrinsic to this judgehost's setup, e.g.
                // missing cgroups.
                $this->disable('judgehost', 'hostname', $this->myhost, $description, $judgeTask['judgetaskid'], $compile_output);
            }
            logmsg(LOG_ERR, $description);

            return false;
        }

        // What does the exitcode mean?
        if (!isset($this->EXITCODES[$retval])) {
            alert('error');
            $description = "Unknown exitcode from compile.sh for s$judgeTask[submitid]: $retval";
            logmsg(LOG_ERR, $description);
            $this->disable('compile_script', 'compile_script_id', $judgeTask['compile_script_id'], $description, $judgeTask['judgetaskid'], $compile_output);

            return false;
        }

        logmsg(LOG_INFO, "  💻 Compilation: ($files[0]) '" . $this->EXITCODES[$retval] . "'");
        $compile_success = ($this->EXITCODES[$retval] === 'correct');

        // Pop the compilation result back into the judging table.
        $args = 'compile_success=' . $compile_success .
            '&output_compile=' . urlencode($this->restEncodeFile($workdir . '/compile.out', $output_storage_limit)) .
            '&compile_metadata=' . urlencode($this->restEncodeFile($workdir . '/compile.meta', false));
        if (isset($metadata['entry_point'])) {
            $args .= '&entry_point=' . urlencode($metadata['entry_point']);
        }

        $url = sprintf('judgehosts/update-judging/%s/%s', urlencode($this->myhost), urlencode((string)$judgeTask['judgetaskid']));
        $this->request($url, 'PUT', $args);

        // Compile error: our job here is done.
        if (!$compile_success) {
            return false;
        }

        touch("$workdir/compile.success");

        return true;
    }

    private function compileAndRunSubmission(array $judgeTask, string $workdirpath): bool
    {
        $startTime = microtime(true);

        $compile_config = dj_json_decode($judgeTask['compile_config']);
        $run_config = dj_json_decode($judgeTask['run_config']);
        $compare_config = dj_json_decode($judgeTask['compare_config']);

        // Set configuration variables for called programs
        putenv('CREATE_WRITABLE_TEMP_DIR=' . (CREATE_WRITABLE_TEMP_DIR ? '1' : ''));

        // These are set again below before comparing.
        putenv('SCRIPTTIMELIMIT=' . $compile_config['script_timelimit']);
        putenv('SCRIPTMEMLIMIT=' . $compile_config['script_memory_limit']);
        putenv('SCRIPTFILELIMIT=' . $compile_config['script_filesize_limit']);

        putenv('MEMLIMIT=' . $run_config['memory_limit']);
        putenv('FILELIMIT=' . $run_config['output_limit']);
        putenv('PROCLIMIT=' . $run_config['process_limit']);
        if ($run_config['entry_point'] !== null) {
            putenv('ENTRY_POINT=' . $run_config['entry_point']);
        } else {
            putenv('ENTRY_POINT');
        }
        $output_storage_limit = (int)$this->djconfigGetValue('output_storage_limit');

        $cpuset_opt = "";
        if (isset($this->options['daemonid'])) {
            $cpuset_opt = '-n ' . dj_escapeshellarg($this->options['daemonid']);
        }

        $workdir = $this->judgingDirectory($workdirpath, $judgeTask);
        $compile_success = $this->compile($judgeTask, $workdir, $workdirpath, $compile_config, $this->options['daemonid'] ?? null, $output_storage_limit);
        if (!$compile_success) {
            return false;
        }

        // TODO: How do we plan to handle these?
        $overshoot = $this->djconfigGetValue('timelimit_overshoot');

        // Check whether we have received an exit signal (but not a graceful exit signal).
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->exitsignalled && !$this->gracefulexitsignalled) {
            logmsg(LOG_NOTICE, "Received HARD exit signal, aborting current judging.");

            // Make sure the domserver knows that we didn't finish this judging.
            $unfinished = $this->request('judgehosts', 'POST', ['hostname' => urlencode($this->myhost)]);
            $unfinished = dj_json_decode($unfinished);
            foreach ($unfinished as $jud) {
                logmsg(LOG_WARNING, "Aborted judging task " . $jud['judgetaskid'] .
                    " due to signal");
            }
            return false;
        }

        return $this->runTestcase($judgeTask, $workdir, $workdirpath, $run_config, $compare_config, $output_storage_limit, $overshoot, $startTime);
    }

    private function runTestcase(
        array $judgeTask,
        string $workdir,
        string $workdirpath,
        array $run_config,
        array $compare_config,
        int $output_storage_limit,
        string $overshoot,
        float $startTime
    ): bool {
        logmsg(LOG_INFO, "  🏃 Running testcase $judgeTask[testcase_id]...");
        $testcasedir = $workdir . "/testcase" . sprintf('%05d', $judgeTask['testcase_id']);
        $tcfile = $this->fetchTestcase($workdirpath, $judgeTask['testcase_id'], $judgeTask['judgetaskid'], $judgeTask['testcase_hash']);
        if ($tcfile === null) {
            // error while fetching testcase
            return false;
        }

        // do the actual test-run
        $combined_run_compare = $compare_config['combined_run_compare'];
        [$run_runpath, $error] = $this->fetchExecutable(
            $workdirpath,
            'run',
            $judgeTask['run_script_id'],
            $run_config['hash'],
            $judgeTask['judgetaskid'],
            $combined_run_compare
        );
        if (isset($error)) {
            return false;
        }

        if ($combined_run_compare) {
            // set to empty string to signal the testcase_run script that the
            // run script also acts as compare script
            $compare_runpath = '';
        } else {
            [$compare_runpath, $error] = $this->fetchExecutable(
                $workdirpath,
                'compare',
                $judgeTask['compare_script_id'],
                $compare_config['hash'],
                $judgeTask['judgetaskid']
            );
            if (isset($error)) {
                return false;
            }
        }

        $hardtimelimit = $run_config['time_limit']
            + overshoot_time($run_config['time_limit'], $overshoot)
            + $run_config['overshoot'];
        $timelimit = [
            'cpu' => [$run_config['time_limit'], $hardtimelimit],
            'wall' => [$run_config['time_limit'], $hardtimelimit],
        ];
        if ($combined_run_compare) {
            // This accounts for wall time spent in the validator. We may likely
            // want to make this configurable in the future. The current factor is
            // under the assumption that the validator has to do approximately the
            // same amount of work wall-time wise as the submission.
            $timelimit['wall'][1] *= 2;
        }

        // While we already set those above to likely the same values from the
        // compile config, we do set them again from the compare config here.
        putenv('SCRIPTTIMELIMIT=' . $compare_config['script_timelimit']);
        putenv('SCRIPTMEMLIMIT=' . $compare_config['script_memory_limit']);
        putenv('SCRIPTFILELIMIT=' . $compare_config['script_filesize_limit']);

        $input = $tcfile['input'];
        $output = $tcfile['output'];
        $passLimit = $run_config['pass_limit'] ?? 1;
        for ($passCnt = 1; $passCnt <= $passLimit; $passCnt++) {
            $nextPass = false;
            if ($passLimit > 1) {
                logmsg(LOG_INFO, "    🔄 Running pass $passCnt...");
            }

            $passdir = $testcasedir . '/' . $passCnt;
            mkdir($passdir, 0755, true);

            // In multi-pass problems, all files in the feedback directory
            // are guaranteed to persist between passes, except `nextpass.in`.
            // So, we recursively copy the feedback directory for every pass
            // after the first (note that $passCnt starts at 1).
            if ($passCnt > 1) {
                $prevPassdir = $testcasedir . '/' . ($passCnt - 1) . '/feedback';
                $this->runCommandSafe(['cp', '-R', $prevPassdir, $passdir . '/']);
                $this->runCommandSafe(['rm', $passdir . '/feedback/nextpass.in']);
            }

            // Copy program with all possible additional files to testcase
            // dir. Use hardlinks to preserve space with big executables.
            $programdir = $passdir . '/execdir';
            if (!$this->runCommandSafe(['mkdir', '-p', $programdir])) {
                error("Could not create directory '$programdir'");
            }

            foreach (glob("$workdir/compile/*") as $compile_file) {
                if (!$this->runCommandSafe(['cp', '-PRl', $compile_file, $programdir])) {
                    error("Could not copy program to '$programdir'");
                }
            }

            $timelimit_str = implode(':', $timelimit['cpu']) . ',' . implode(':', $timelimit['wall']);
            $run_command_parts = [LIBJUDGEDIR . '/testcase_run.sh'];
            if (isset($this->options['daemonid'])) {
                $run_command_parts[] = '-n';
                $run_command_parts[] = $this->options['daemonid'];
            }
            array_push(
                $run_command_parts,
                $input,
                $output,
                $timelimit_str,
                $passdir,
                $run_runpath,
                $compare_runpath,
                $compare_config['compare_args']
            );
            $this->runCommandSafe($run_command_parts, $retval, log_nonzero_exitcode: false);

            // What does the exitcode mean?
            if (!isset($this->EXITCODES[$retval])) {
                alert('error');
                error("Unknown exitcode ($retval) from testcase_run.sh for s$judgeTask[submitid]");
            }
            $result = $this->EXITCODES[$retval];

            // Try to read metadata from file
            $runtime = null;
            $metadata = $this->readMetadata($passdir . '/program.meta');

            if (isset($metadata['time-used']) && array_key_exists($metadata['time-used'], $metadata)) {
                $runtime = $metadata[$metadata['time-used']];
            }

            if ($result === 'compare-error') {
                $compareMeta = $this->readMetadata($passdir . '/compare.meta');
                $compareExitCode = 'n/a';
                if (isset($compareMeta['exitcode'])) {
                    $compareExitCode = $compareMeta['exitcode'];
                }
                if ($combined_run_compare) {
                    logmsg(LOG_ERR, "comparing failed for combined run/compare script '" . $judgeTask['run_script_id'] . "'");
                    $description = 'combined run/compare script ' . $judgeTask['run_script_id'] . ' crashed with exit code ' . $compareExitCode . ", expected one of 42/43";
                    $this->disable('run_script', 'run_script_id', $judgeTask['run_script_id'], $description, $judgeTask['judgetaskid']);
                } else {
                    logmsg(LOG_ERR, "comparing failed for compare script '" . $judgeTask['compare_script_id'] . "'");
                    logmsg(LOG_ERR, "compare script meta data:\n" . dj_file_get_contents($passdir . '/compare.meta'));
                    $description = 'compare script ' . $judgeTask['compare_script_id'] . ' crashed with exit code ' . $compareExitCode . ", expected one of 42/43";
                    $this->disable('compare_script', 'compare_script_id', $judgeTask['compare_script_id'], $description, $judgeTask['judgetaskid']);
                }
                return false;
            }

            $new_judging_run = [
                'runresult' => urlencode($result),
                'start_time' => urlencode((string)$startTime),
                'end_time' => urlencode((string)microtime(true)),
                'runtime' => urlencode((string)$runtime),
                'output_run' => $this->restEncodeFile($passdir . '/program.out', $output_storage_limit),
                'output_error' => $this->restEncodeFile($passdir . '/program.err', $output_storage_limit),
                'output_system' => $this->restEncodeFile($passdir . '/system.out', $output_storage_limit),
                'metadata' => $this->restEncodeFile($passdir . '/program.meta', false),
                'output_diff' => $this->restEncodeFile($passdir . '/feedback/judgemessage.txt', $output_storage_limit),
                'hostname' => $this->myhost,
                'testcasedir' => $testcasedir,
                'compare_metadata' => $this->restEncodeFile($passdir . '/compare.meta', false),
            ];

            if (file_exists($passdir . '/feedback/teammessage.txt')) {
                $new_judging_run['team_message'] = $this->restEncodeFile($passdir . '/feedback/teammessage.txt', $output_storage_limit);
            }

            if ($passLimit > 1) {
                $walltime = $metadata['wall-time'] ?? '?';
                logmsg(LOG_INFO, ' ' . ($result === 'correct' ? "   \033[0;32m✔\033[0m" : "   \033[1;31m✗\033[0m")
                    . '  ...done in ' . $walltime . 's (CPU: ' . $runtime . 's), result: ' . $result);
            }

            if ($result !== 'correct') {
                break;
            }
            if (file_exists($passdir . '/feedback/nextpass.in')) {
                $input = $passdir . '/feedback/nextpass.in';
                $nextPass = true;
            } else {
                break;
            }
        }
        if ($nextPass) {
            $description = 'validator produced more passes than allowed ($passLimit)';
            $this->disable('compare_script', 'compare_script_id', $judgeTask['compare_script_id'], $description, $judgeTask['judgetaskid']);
            return false;
        }

        $ret = true;
        if ($result === 'correct') {
            // Correct results get reported asynchronously, so we can continue judging in parallel.
            $this->reportJudgingRun($judgeTask, $new_judging_run, asynchronous: true);
        } else {
            // This run was incorrect, only continue with the remaining judge tasks
            // if we are told to do so.
            $needsMoreWork = $this->reportJudgingRun($judgeTask, $new_judging_run, asynchronous: false);
            $ret = (bool)$needsMoreWork;
        }

        if ($passLimit == 1) {
            $walltime = $metadata['wall-time'] ?? '?';
            logmsg(LOG_INFO, ' ' . ($result === 'correct' ? " \033[0;32m✔\033[0m" : " \033[1;31m✗\033[0m")
                . '  ...done in ' . $walltime . 's (CPU: ' . $runtime . 's), result: ' . $result);
        }

        // done!
        return $ret;
    }

    private function reportJudgingRun(array $judgeTask, array $new_judging_run, bool $asynchronous): ?string
    {
        $judgeTaskId = $judgeTask['judgetaskid'];

        if ($asynchronous && function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                logmsg(LOG_WARNING, "Could not fork to report result for jt$judgeTaskId asynchronously, reporting synchronously.");
                // Fallback to synchronous reporting by continuing in this process.
            } elseif ($pid > 0) {
                // Parent process, nothing more to do here.
                logmsg(LOG_DEBUG, "Forked a child with PID $pid to report judging run for jt$judgeTaskId.");
                return null;
            } else {
                // Child process: reset signal handlers to default.
                pcntl_signal(SIGTERM, SIG_DFL);
                pcntl_signal(SIGINT, SIG_DFL);
                pcntl_signal(SIGHUP, SIG_DFL);
                pcntl_signal(SIGUSR1, SIG_DFL);

                // The child should use its own curl handle to avoid issues with sharing handles
                // between processes.
                $endpoint = $this->endpoint;
                $this->endpoint['ch'] = $this->setupCurlHandle($endpoint['user'], $endpoint['pass']);
            }
        } elseif ($asynchronous) {
            logmsg(LOG_WARNING, "pcntl extension not available, reporting result for jt$judgeTaskId synchronously.");
        }

        $isChild = isset($pid) && $pid === 0;

        $success = false;
        for ($i = 0; $i < 5; $i++) {
            if ($i > 0) {
                $sleep_ms = 100 + random_int(200, ($i + 1) * 1000);
                dj_sleep(0.001 * $sleep_ms);
            }
            $response = $this->request(
                sprintf(
                    'judgehosts/add-judging-run/%s/%s',
                    $new_judging_run['hostname'],
                    urlencode((string)$judgeTaskId)
                ),
                'POST',
                $new_judging_run,
                false
            );
            if ($response !== null) {
                logmsg(LOG_DEBUG, "Adding judging run result for jt$judgeTaskId successful.");
                $success = true;
                break;
            }
            logmsg(LOG_WARNING, "Failed to report jt$judgeTaskId in attempt #" . ($i + 1) . ".");
        }

        if (!$success) {
            $message = "Final attempt of uploading jt$judgeTaskId was unsuccessful, giving up.";
            if ($isChild) {
                error($message);
            } else {
                warning($message);
                return null;
            }
        }

        if ($isChild) {
            exit(0);
        }

        return $response;
    }

    private function fetchTestcase(string $workdirpath, string $testcase_id, int $judgetaskid, string $testcase_hash): ?array
    {
        // Get both in- and output files, only if we didn't have them already.
        $tcfile = [];
        $bothFilesExist = true;
        foreach (['input', 'output'] as $inout) {
            $testcasedir = $workdirpath . '/testcase/' . $testcase_id;
            if (!is_dir($testcasedir)) {
                mkdir($testcasedir, 0755, true);
            }
            $tcfile[$inout] = $testcasedir . '/'
                . $testcase_hash . '.' .
                ($inout == 'input' ? 'in' : 'out');
            if (!file_exists($tcfile[$inout])) {
                $bothFilesExist = false;
            }
        }
        if ($bothFilesExist) {
            return $tcfile;
        }
        $content = $this->request(sprintf('judgehosts/get_files/testcase/%s', $testcase_id), 'GET', '', false);
        if ($content === null) {
            $error = 'Download of testcase failed for case ' . $testcase_id . ', check your problem integrity.';
            logmsg(LOG_ERR, $error);
            $this->disable('testcase', 'testcaseid', $testcase_id, $error, $judgetaskid);
            return null;
        }
        $files = dj_json_decode($content);
        unset($content);
        foreach ($files as $file) {
            $filename = $tcfile[$file['filename']];
            file_put_contents($filename, base64_decode($file['content']));
        }
        unset($files);

        logmsg(LOG_INFO, "  💾 Fetched new testcase $testcase_id.");
        return $tcfile;
    }

    private function initsignals(): void
    {
        pcntl_signal(SIGTERM, [self::class, 'signalHandler']);
        pcntl_signal(SIGINT, [self::class, 'signalHandler']);
        pcntl_signal(SIGHUP, [self::class, 'signalHandler']);
        pcntl_signal(SIGUSR1, [self::class, 'signalHandler']);
    }
}

$daemon = new JudgeDaemon();
$daemon->run();
