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
$res = $DB->q('SELECT * FROM submission LEFT JOIN judging USING(submitid)
        WHERE (valid = 1 OR valid IS NULL) AND team = %s ORDER BY submittime', $login);

if($res->count() == 0) {
	echo "<em>Nothing submitted yet.</em>";
} else {
	echo "<table>\n";
	while($srow = $res->next()) {

		// abstract this into a general print-submissions function??
		
		echo "<tr><td><a href=\"submission.php?id=".$srow['submitid']."\">".$srow['submitid']."</a>".
			"</td><td>".printtime($srow['submittime']).
			"</td><td>".$srow['probid'].
			"</td><td>".$srow['langid'].
			"</td><td class=\"sol-";
		
		if(! @$srow['judger'] ) {
			echo "queued\">queued";
		} elseif( @!$srow['result'] ) {
			echo "queued\">judging";
		} elseif( $srow['result'] == 'correct') {
			echo "correct\">correct";
		} else {
			echo "incorrect\">".$srow['result'];
		}

		echo "</td><td>".@$srow['judger'];
		echo "</td></tr>\n";
	}
	echo "</table>\n\n";
}


require('../footer.php');
