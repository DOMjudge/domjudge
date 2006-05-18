<?php
/**
 * Common page header.
 * Before including this, one can set $title, $refresh and $popup.
 *
 * $Id$
 */

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?= DOMJUDGE_VERSION ?> -->
<?php

	if( isset($refresh) ) {
		echo '<meta http-equiv="refresh" content="' .
			( isset($popup) ? addUrl($refresh, $popupTag) : $refresh ) .
			"\" />\n";
	}
	echo "<title>" . $title . "</title>\n";

?>
<link rel="stylesheet" href="style.css" type="text/css" />
<script type="text/javascript">
function popUp(URL) {
	var w = window.open(URL, 'ALERT', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=300,height=200');
	w.focus();
}
</script>
</head>
<?php

echo '<body';
if( isset($popup) && $popup ) echo " onLoad=\"javascript:popUp('popup.php')\"";
echo ">\n\n";
