<?php
/**
 * View team details
 *
 * $Id$
 */

require('init.php');
$title = 'Team';
require('../header.php');

$login = $_GET['id'];
if(preg_match('/\W/', $login)) {
	error("Login contains invalid chars");
}

echo "<h1>Team $login</h1>\n\n";

$row = $DB->q('TUPLE SELECT t.*,c.name as catname
	FROM team t LEFT JOIN category c ON(t.category=c.catid) WHERE login = %s', $login);
?>

<table>
<tr><td>Login:</td><td><?=$row['login']?></td></tr>
<tr><td>Name:</td><td><?=$row['name']?></td></tr>
<tr><td>Category:</td><td><?=$row['category'].' - '.$row['catname']?></td></tr>
<tr><td>IP-address:</td><td><?=@$row['ipaddress'] ? $row['ipaddress'].' - '.gethostbyaddr($row['ipaddress']):''?></td></tr>
</table>

<h3>Submissions</h3>

<?php

getSubmissions('team', $login);

require('../footer.php');
