<?php
/**
 * Common page header.
 * Before including this, one can set $title, $refresh and $popup.
 */

echo '<?xml version="1.0" encoding="iso-8859-1" ?>'."\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
<?php
	if( isset($refresh) ) {
?>
	<meta http-equiv="refresh" content="<?
		if( isset($popup) ) {
			echo addUrl($refresh, $popupTag);
		} else {
			echo $refresh;
		}
		?>" />
<?php
	}
?>
	<title><?= $title ?></title>
	<link rel="stylesheet" href="style.css" type="text/css" />
	
	<script language="JavaScript">
	function popUp(URL) {
		var w = window.open(URL, 'ALERT', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=300,height=200');
		w.focus();
	}
	</script>
</head>
<body <?php if( isset($popup) && $popup ) { echo "onLoad=\"javascript:popUp('popup.php')\""; } ?>>
