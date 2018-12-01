<?php declare(strict_types=1);
/**
 * View the details of a specific rejudging
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$id = getRequestID();

$title = 'Rejudging r'.@$id;

require(LIBWWWDIR . '/header.php');

if (! $id) {
    error("Missing or invalid rejudging id");
}

$rejdata = $DB->q('TUPLE SELECT * FROM rejudging WHERE rejudgingid=%i', $id);

if (! $rejdata) {
    error("Missing rejudging data");
}

if (isset($_REQUEST['apply']) || isset($_REQUEST['cancel'])) {
    $request = isset($_REQUEST['apply']) ? 'apply' : 'cancel';

    $time_start = microtime(true);

    // no output buffering... we want to see what's going on real-time
    header('X-Accel-Buffering: no');
    echo "<br/><p>Applying rejudge may take some time, please be patient:</p>\n";
    while (ob_get_level()) {
        ob_end_flush();
    }
    ob_implicit_flush(1);

    // clear GET array because otherwise the eventlog subrequest will still include the rejudging id
    $_GET = array();
    echo "<p>\n";

    rejudging_finish($id, $request, $userdata['userid'], true);

    echo "\n</p>\n";

    // Start output buffering again to not crash the FallbackController
    ob_start();

    $time_end = microtime(true);

    echo "<p>Rejudging <a href=\"rejudging.php?id=" . urlencode((string)$id) .
        "\">r$id</a> ".($request=='apply' ? 'applied' : 'canceled').
        " in ".sprintf('%.2f', $time_end - $time_start)." seconds.</p>\n\n";

    require(LIBWWWDIR . '/footer.php');
    return;
}
