<?php

require_once("common-config.php");

// Default config

// Run solutions in a chroot environment? The chroot is an essential
// part of judgehost security in DOMjudge. It should be enabled
// except when needed for testing or debugging. No security
// guarantees can be given when it is disabled.
define('USE_CHROOT', true);

// Optional script to run for creating/destroying chroot environment,
// leave empty to disable. This example script can be used to support
// Oracle (Sun) Java with a chroot (edit the script first!).
// define('CHROOT_SCRIPT', 'chroot-startstop.sh');
define('CHROOT_SCRIPT', '');
