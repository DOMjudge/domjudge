<?php
/**
 * View team details
 *
 * $Id$
 */

require('init.php');
$refresh = '15;url='.$_SERVER["REQUEST_URI"];
$title = 'Team';
require('../header.php');
require('menu.php');

$login = $_GET['id'];
if(preg_match('/\W/', $login)) {
	error("Login contains invalid chars");
}

$row = $DB->q('TUPLE SELECT t.*,c.name as catname
	FROM team t LEFT JOIN category c ON(t.category=c.catid) WHERE login = %s', $login);

echo "<h1>Team ".htmlentities($row['name'])."</h1>\n\n";

?>

<table>
<tr><td>Login:</td><td class="teamid"><?=$row['login']?></td></tr>
<tr><td>Name:</td><td><?=htmlentities($row['name'])?></td></tr>
<tr><td>Category:</td><td><?=(int)$row['category'].' - '.htmlentities($row['catname'])?></td></tr>
<tr><td>IP-address:</td><td><?=@$row['ipaddress'] ? htmlspecialchars($row['ipaddress']) .
	' - '.printhost(gethostbyaddr($row['ipaddress']), TRUE):''?></td></tr>
</table>

<h3>Submissions</h3>

<?php

getSubmissions('team', $login);

require('../footer.php');
