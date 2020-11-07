<?php declare(strict_types=1);

require_once("common-config.php");

// Enable this to support removing time intervals from the contest.
// Although we use this feature at the ICPC World Finals, we strongly
// discourage using it, and we don't guarantee the code is completely
// bug-free. This code is rarely tested!
define('ALLOW_REMOVED_INTERVALS', false);

// Specify URL of the iCAT webinterface. Uncommenting this will enable
// the ICPC Analytics iCAT integration for jury members.
//define('ICAT_URL', 'http://icat.example.com/icat/');
