<?php

require('init.php');

$refresh = '15;url='.getBaseURI().'team/menu.php';

header("Refresh: " . $refresh);

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
	<meta http-equiv="refresh" content="<?=$refresh?>" />
<link rel="stylesheet" href="style.css" type="text/css" />
<title>DOMjudge menu</title>
</head>
<body>
<?php


/* (new) clarification info */
$res = $DB->q('KEYTABLE SELECT type AS ARRAYKEY, COUNT(*) AS count FROM team_unread
               WHERE teamid = %s GROUP BY type', $login);

echo "<div id=\"menutop\">\n";

if ( isset($res['submission']) ) {
	echo '<a target="_top" class="new" href="index.php">submissions (' .
		(int)$res['submission']['count'] . " new)</a>\n";
} else {
	echo "<a target=\"_top\" href=\"index.php\">submissions</a>\n";
}

if ( isset($res['clarification']) ) {
	echo '<a target="_top" class="new" href="clarifications.php">clarifications (' .
		(int)$res['clarification']['count'] . " new)</a>\n";
} else {
	echo "<a target=\"_top\" href=\"clarifications.php\">clarifications</a>\n";
}

echo "<a target=\"_top\" href=\"scoreboard.php\">scoreboard</a>\n";

if ( ENABLE_WEBSUBMIT_SERVER ) {
	echo "<a target=\"_top\" href=\"websubmit.php\">submit</a>\n";
}

echo "\n</div>\n\n";

putClock();

require('../footer.php');
