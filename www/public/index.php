<?php
/**
 * Produce a total score. Call with URL parameter 'static' for
 * output suitable for static HTML pages.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = "Scoreboard";
$isstatic = isset($_REQUEST['static']);

// set auto refresh
$refresh = array(
    "after" => "30",
    "url" => "./",
);
if ($isstatic) {
    $refresh['url'] .= '?static=1';
}

// This reads and sets a cookie, so must be called before headers are sent.
$filter = initScorefilter();

$menu = !$isstatic;
require(LIBWWWDIR . '/header.php');

if ($isstatic && isset($_REQUEST['contest'])) {
    if ($_REQUEST['contest'] === 'auto') {
        $a = null;
        foreach ($cdatas as $c) {
            if (!$c['public'] || !$c['enabled']) {
                continue;
            }
            if (is_null($a) || $a < $c['activatetime']) {
                $a = $c['activatetime'];
                $cdata = $c;
            }
        }
    } else {
        $found = false;
        foreach ($cdatas as $c) {
            if ($c['externalid'] == $_REQUEST['contest'] ||
                $c['cid'] == $_REQUEST['contest']) {
                $cdata = $c;
                $found = true;
                break;
            }
        }
        if (!$found) {
            error("Specified contest not found");
        }
    }
}

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, null, $isstatic, $filter);

echo "<script type=\"text/javascript\">initFavouriteTeams();</script>";

require(LIBWWWDIR . '/footer.php');
