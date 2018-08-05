<?php
/**
 * Include required files.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once('../configure.php');

$pagename = basename($_SERVER['PHP_SELF']);

define('IS_JURY', true);
define('IS_PUBLIC', false);

require_once(LIBDIR . '/init.php');

setup_database_connection();

require_once(LIBWWWDIR . '/common.php');
require_once(LIBWWWDIR . '/print.php');
require_once(LIBWWWDIR . '/forms.php');
require_once(LIBWWWDIR . '/printing.php');
require_once(LIBWWWDIR . '/auth.php');

logged_in();
define('IS_ADMIN', checkrole('admin'));

if (!isset($REQUIRED_ROLES)) {
    $REQUIRED_ROLES = array('jury', 'balloon');
}
$allowed = false;
foreach ($REQUIRED_ROLES as $role) {
    if (checkrole($role)) {
        $allowed = true;
    }
}
if (!$allowed) {
    error("You do not have permission to perform that action (Missing role(s): " . implode($REQUIRED_ROLES, ',') . ")");
}

require_once(LIBWWWDIR . '/common.jury.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    error("POST data exceeded php.ini's 'post_max_size' directive (currently set to " . ini_get('post_max_size') . ')');
}

$cdatas = getCurContests(true, null, true);
$cids = array_keys($cdatas);

// List of executable script types, used in various places:
$executable_types = array('compare' => 'compare',
                          'compile' => 'compile',
                          'run'     => 'run');

// If the cookie has a existing contest, use it
if (isset($_COOKIE['domjudge_cid'])) {
    if (isset($cdatas[$_COOKIE['domjudge_cid']])) {
        $cid = $_COOKIE['domjudge_cid'];
        $cdata = $cdatas[$cid];
    }
} elseif (count($cids) >= 1) {
    // Otherwise, select the first contest
    $cid = $cids[0];
    $cdata = $cdatas[$cid];
}

// Data to be sent as AJAX updates:
$updates = array(
    'clarifications' =>
    (empty($cid) ? array() :
      $DB->q('TABLE SELECT clarid, submittime, sender, recipient, probid, body
              FROM clarification
              WHERE sender IS NOT NULL AND cid = %i AND answered = 0', $cid)),
    'judgehosts' =>
    $DB->q('TABLE SELECT hostname, polltime
            FROM judgehost
            WHERE active = 1 AND unix_timestamp()-polltime >= %i',
           dbconfig_get('judgehost_critical', 120)),
    'rejudgings' =>
    $DB->q('TABLE SELECT rejudgingid
            FROM rejudging
            WHERE endtime IS NULL'),
    'internal_error' =>
    $DB->q('TABLE SELECT errorid
            FROM internal_error
            WHERE status=%s', 'open'),
);

// set up twig
// require_once(LIBVENDORDIR . '/autoload.php');
// Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem(array('.', LIBWWWDIR));
$twig = new Twig_Environment($loader);

$twig_safe = array('is_safe' => array('html'));
$twig->addFilter(new Twig_SimpleFilter('host', 'printhost', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('humansize', 'printsize', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('time', 'printtime', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('timediff', 'printtimediff', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('result', 'printresult', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('jud_busy', 'printjudgingbusy', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('yesno', 'printyn', $twig_safe));
$twig->addFilter(new Twig_SimpleFilter('description_expand', 'descriptionExpand', $twig_safe));
unset($twig_safe);
