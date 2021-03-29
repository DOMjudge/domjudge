<?php declare(strict_types=1);

// Log to syslog facility; do not define to disable.
define('SYSLOG', LOG_LOCAL0);

// Normally submissions don't have any directory they can write to,
// at least when the chroot is enabled. However, some languages might
// require a temporary (writable) directory to put stuff in (for example
// R). Setting this to true will create a directory "write_tmp" where
// the submission can write to. Also the environment variable TMPDIR will
// be set to this directory
define('CREATE_WRITABLE_TEMP_DIR', getenv('DOMJUDGE_CREATE_WRITABLE_TEMP_DIR') ? true : false);
