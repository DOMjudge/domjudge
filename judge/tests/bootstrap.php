<?php declare(strict_types=1);

// Prevent the judgedaemon from running when included
define('DOMJUDGE_TESTING', true);

// Define SCRIPT_ID early so lib.error.php can use it
define('SCRIPT_ID', 'judge-test');

$domjudgeRoot = dirname(__DIR__, 2);

// Include composer autoloader
require_once $domjudgeRoot . '/webapp/vendor/autoload.php';

// Define required path constants
define('DOMJUDGE_VERSION', 'test');
define('BINDIR', $domjudgeRoot . '/bin');
define('ETCDIR', $domjudgeRoot . '/etc');
define('LIBDIR', $domjudgeRoot . '/lib');
define('LIBJUDGEDIR', $domjudgeRoot . '/lib/judge');

$tempDir = sys_get_temp_dir() . '/domjudge-test';
define('LOGDIR', $tempDir . '/log');
define('RUNDIR', $tempDir . '/run');
define('TMPDIR', $tempDir . '/tmp');
define('JUDGEDIR', $tempDir . '/judgings');
define('CHROOTDIR', '/chroot/domjudge');
define('RUNUSER', getenv('RUNUSER') ?: 'domjudge-run');
define('RUNGROUP', getenv('RUNGROUP') ?: 'domjudge-run');
define('LOGFILE', LOGDIR . '/judge.test.log');

// Create temp directories
foreach ([LOGDIR, RUNDIR, TMPDIR, JUDGEDIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Include the judgedaemon (DOMJUDGE_TESTING prevents it from running)
require_once $domjudgeRoot . '/judge/judgedaemon.main.php';

// Include lib.error.php for logmsg()
require_once LIBDIR . '/lib.error.php';

// Suppress verbose output during tests
global $verbose;
$verbose = LOG_ERR;
