<?php declare(strict_types=1);

require_once("common-config.php");

// Enable this to support removing time intervals from the contest.
// Although we use this feature at the ICPC World Finals, we strongly
// discourage using it, and we don't guarantee the code is completely
// bug-free. This code is rarely tested!
define('ALLOW_REMOVED_INTERVALS', false);
