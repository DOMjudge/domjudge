<?php
/**
 * View team details
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = $_REQUEST['id'];

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id);
$title = 'Team '.htmlspecialchars(@$id);

if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");

if ( isset($_POST['cmd']) && $_POST['cmd'] == 'rejudge' ) {
	rejudge('submission.team',$id);
	header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
	exit;
}

$row = $DB->q('TUPLE SELECT t.*,c.name as catname FROM team t
               LEFT JOIN category c ON(t.category=c.catid) WHERE login = %s', $id);

require('../header.php');
require('menu.php');

echo "<h1>Team ".htmlentities($row['name'])."</h1>\n\n";

?>

<table>
<tr><td>Login:     </td><td class="teamid"><?=$row['login']?></td></tr>
<tr><td>Name:      </td><td><?=htmlentities($row['name'])?></td></tr>
<tr><td>Category:  </td><td><?=(int)$row['category'].
	' - '.htmlentities($row['catname'])?></td></tr>
<tr><td>Host:</td><td><?=@$row['ipaddress'] ? htmlspecialchars($row['ipaddress']).
	' - '.printhost(gethostbyaddr($row['ipaddress']), TRUE):''?></td></tr>
</table>

<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value=" REJUDGE ALL! "
 onclick="return confirm('Rejudge all submissions for this team?')" />
</p>
</form>

<h3>Submissions</h3>

<?php

putSubmissions('team', $id, TRUE);

require('../footer.php');
