<?php

require('init.php');

header("Refresh: 30;url=" . getBaseURI() . "jury/menu.php");

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
	<link rel="stylesheet" href="style.css" type="text/css" />
	<title>DOMjudge menu</title>
</head>
<body>
<?

$cid = getCurContest();

$cnew = $DB->q('VALUE SELECT COUNT(*) FROM clarification
                WHERE sender IS NOT NULL AND cid = %i AND answered = 0
                ORDER BY submittime DESC', $cid);

?>
<div id="menutop">
<a target="_TOP" href="index.php">home</a>
<a target="_TOP" href="problems.php">problems</a>
<a target="_TOP" href="judgehosts.php">judgehosts</a>
<a target="_TOP" href="teams.php">teams</a>
<?	if ( $cnew ) { ?>
<a target="_TOP" class="new" href="clarifications.php">clarifications (<?=$cnew?> new)</a>
<?	} else { ?>
<a target="_TOP" href="clarifications.php">clarifications</a>
<?	} ?>
<a target="_TOP" href="submissions.php">submissions</a>
<a target="_TOP" href="scoreboard.php">scoreboard</a>
</div>

<?php putClock();
// $Id$

require('../footer.php');
