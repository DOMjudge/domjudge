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
<?


/* (new) clarification info */
$res = $DB->q('KEYTABLE SELECT type AS ARRAYKEY, COUNT(*) AS count FROM team_unread
               WHERE team = %s GROUP BY type', $login);

?>
<div id="menutop">
<?	if ( isset($res['submission']) ) { ?>
<a target="_TOP" class="new" href="index.php">submissions (<?=$res['submission']['count']?>)</a>
<?	} else { ?>
<a target="_TOP" href="index.php">submissions</a>
<?	}
	if ( isset($res['clarification']) ) {
?><a target="_TOP" class="new" href="clarifications.php">clarifications (<?=$res['clarification']['count']?> new)</a>
<?	} else { ?>
<a target="_TOP" href="clarifications.php">
clarifications</a>
<?	} ?>
<a target="_TOP" href="scoreboard.php">scoreboard</a>
<? if ( ENABLE_WEBSUBMIT_SERVER ) { ?>
<a target="_TOP" href="websubmit.php">submit</a>
<? } ?>
</div>

<?

putClock();

require('../footer.php');
