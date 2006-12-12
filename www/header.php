<?php
/**
 * Common page header.
 * Before including this, one can set $title, $refresh and $popup.
 *
 * $Id$
 */

if ( isset($refresh) ) {
	header('Refresh: ' . $refresh);
}

echo '<?xml version="1.0" encoding="iso-8859-1" ?>' . "\n";

if(!isset($menu)) {
	$menu = true;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
	<!-- DOMjudge version <?=DOMJUDGE_VERSION?> -->
<title><?=$title?></title>
<link rel="stylesheet" href="style.css" type="text/css" />
</head>
<body>

<?	if($menu) { ?>
<iframe id="menubox" src="menu.php"></iframe>
<?	}	?>

