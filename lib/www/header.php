<?php
/**
 * Common page header.
 * Before including this, one can set $title, $refresh,
 * $printercss, $jscolor and $menu.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if (!defined('DOMJUDGE_VERSION')) die("DOMJUDGE_VERSION not defined.");

header('Content-Type: text/html; charset=' . DJ_CHARACTER_SET);

/* Prevent clickjacking by forbidding framing in modern browsers.
 * Really want to frame DOMjudge? Then change DENY to SAMEORIGIN
 * or even comment out the header altogether. For the public
 * interface there's no risk, and embedding the scoreboard in a
 * frame may be useful.
 */
if ( ! IS_PUBLIC ) header('X-Frame-Options: DENY');

$refresh_cookie = (!isset($_COOKIE["domjudge_refresh"]) || (bool)$_COOKIE["domjudge_refresh"]);

if(!isset($menu)) {
	$menu = true;
}
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
<head>
	<!-- DOMjudge version <?php echo DOMJUDGE_VERSION?> -->
<meta charset="<?php echo DJ_CHARACTER_SET?>"/>
<title><?php echo $title?></title>
<link rel="icon" href="../images/favicon.png" type="image/png" />
<link rel="stylesheet" href="../style.css" type="text/css" />
<link rel="stylesheet" href="../css/octicons/octicons.css" />
<script type="text/javascript" src="../js/jquery.min.js"></script>
<?php
if ( IS_JURY ) {
	echo "<link rel=\"stylesheet\" href=\"../style_jury.css\" type=\"text/css\" />\n";
	if (isset($printercss)) {
		echo "<link rel=\"stylesheet\" href=\"../style_printer.css\" type=\"text/css\" media=\"print\" />\n";
	}
	echo "<script type=\"text/javascript\" src=\"../js/jury.js\"></script>\n";
	echo "<script type=\"text/javascript\" src=\"../js/js.cookie.min.js\"></script>\n";
	if (isset($jscolor)) {
		echo "<script type=\"text/javascript\" src=\"" .
		"../js/jscolor.js\"></script>\n";
	}
	if (isset($jqtokeninput)) {
		echo "<link rel=\"stylesheet\" href=\"../token-input.css\" type=\"text/css\" />";
		echo "<script type=\"text/javascript\" src=\"../js/jquery.tokeninput.min.js\"></script>\n";
	}
	echo "<script type=\"text/javascript\" src=\"" .
		"../js/sorttable.js\"></script>\n";
}
echo "<script type=\"text/javascript\" src=\"../js/domjudge.js\"></script>\n";

if ( ! empty($extrahead) ) echo $extrahead;
?>
<?php
if ( isset($refresh) ) {
?>
<script type="text/javascript">
var refreshHandler = null;
var refreshEnabled = false;
function enableRefresh() {
	if (refreshEnabled) {
		return;
	}
	refreshHandler = setTimeout(function () {
		window.location = '<?php echo $refresh['url']; ?>';
	}, <?php echo $refresh['after'] * 1000; ?>);
	refreshEnabled = true;
	window.Cookies && Cookies.set('domjudge_refresh', 1);
}

function disableRefresh() {
	if (!refreshEnabled) {
		return;
	}
	clearTimeout(refreshHandler);
	refreshEnabled = false;
	window.Cookies && Cookies.set('domjudge_refresh', 0);
}

function toggleRefresh() {
	if ( refreshEnabled ) {
		disableRefresh();
	} else {
		enableRefresh();
	}

	var text = refreshEnabled ? 'Disable refresh' : 'Enable refresh';
	$('#refresh-toggle').val(text);
}

<?php
if ( IS_JURY ) {
?>
$(function () {
	$('#refresh-toggle').on('click', function () {
		toggleRefresh();
	});
});

<?php
}
if ($refresh_cookie) {
?>
enableRefresh();
<?php
}
?>
</script>
<?php
}
?>
</head>
<?php

if ( IS_JURY ) {
	global $pagename;
	echo "<body onload=\"setInterval('updateMenu(" .
		(int)($pagename=='clarifications.php' && $refresh_cookie) . ", " .
		(int)($pagename=='judgehosts.php'     && $refresh_cookie) . ", " .
		(int)($pagename=='rejudgings.php'     && $refresh_cookie) . ")', 20000); " .
		"updateMenu(0,0,0)\">\n";
} else {
	echo "<body>\n";
}

/* NOTE: here a local menu.php is included
 *       both jury and team have their own menu.php
 */
if ( $menu ) include("menu.php");
