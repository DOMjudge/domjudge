<?php

require('init.php');
require_once('popupcheck.php');

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
<?	$refresh = '3;url='.getBaseURI().'team/menu.php';
	echo '<meta http-equiv="refresh" content="'
		. addUrl($refresh, $popupTag)
		. "\" />\n";
?>
<link rel="stylesheet" href="style.css" type="text/css" />
<script type="text/javascript">
	function popUp(URL) {
		var w = window.open(URL, 'ALERT', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=300,height=200');
		w.focus();
	}
</script>
</head>
<body>
<?

echo '<body';
if( isset($popup) && $popup )
	echo " onLoad=\"javascript:popUp('"
		. addUrl('popup.php', $popup)
		. "')\">\n\n";


/* (new) clarification info */
$res = $DB->q('KEYTABLE SELECT `type` AS ARRAYKEY, COUNT(*) AS `count`'
			. ' FROM `team_unread`'
			. 'WHERE `team` = %s '
			. 'GROUP BY `type`'
			, $login
			);
?>
<div id="menutop">
<?	if ( isset($res['submission']) ) { ?>
<a target="content" class="new" href="submissions.php">submissions (<?=$res['submission']['count']?>)</a>
<?	} else { ?>
<a target="content" href="submissions.php">submissions</a>
<?	}
	if ( isset($res['clarification']) ) {
?><a target="content" class="new" href="clarifications.php">clarifications (<?=$res['clarification']['count']?> new)</a>
<?	} else { ?>
<a target="content" href="clarifications.php">
clarifications</a>
<?	} ?>
<a target="content" href="scoreboard.php">scoreboard</a>
<? if ( ENABLEWEBSUBMIT ) { ?>
<a target="content" href="websubmit.php">submit</a>
<? } ?>
</div>

<?

putClock();

require('../footer.php');
