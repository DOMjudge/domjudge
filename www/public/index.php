<?php
/**
 * Produce a total score. Call with parameter 'static' for
 * output suitable for static HTML pages.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');
$title="Scoreboard";
// set auto refresh
$refresh="30;url=./";

// parse filter options
$filter = array();
if ( !isset($_GET['clear']) ) {
	foreach( array('affilid', 'country', 'categoryid') as $type ) {
		if ( !empty($_GET[$type]) ) $filter[$type] = $_GET[$type];
	}
	if ( count($filter) ) $refresh .= '?' . http_build_query($filter);
}
$extrahead = <<<HEAD
<script type="text/javascript">
<!--
function collapse(x){
	var oTemp=document.getElementById("detail"+x);
	if (oTemp.style.display=="none") {
		oTemp.style.display="block";
	} else {
		oTemp.style.display="none";
	}
}
// -->
</script>
HEAD;

$menu = false;
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/scoreboard.php');

$isstatic = @$_SERVER['argv'][1] == 'static' || isset($_REQUEST['static']);

if ( ! $isstatic ) {
	echo "<div id=\"menutopright\">\n";
	putClock();
	echo "</div>\n";
}

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, null, $isstatic, $filter);

require(LIBWWWDIR . '/footer.php');
