<?php
/**
 * View team details
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_REQUEST['id'];

require('init.php');

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id).
		(isset($_GET['restrict'])?'&restrict='.urlencode($_GET['restrict']):'');
}

$title = 'Team '.htmlspecialchars(@$id);


if ( isset($pcmd) && $pcmd == 'rejudge' ) {
	if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");
	rejudge('submission.teamid',$id);
	header('Location: '.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id));
	exit;
}

require('../header.php');

if ( IS_ADMIN && !empty($cmd) ):

	require('../forms.php');
	
	echo "<h2>" . ucfirst($cmd) . " team</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n" .
		"<tr><td>Login:</td><td class=\"teamid\">";

	if ( $cmd == 'edit' ) {
		$row = $DB->q('TUPLE SELECT * FROM team WHERE login = %s',
			$_GET['id']);
		echo addHidden('keydata[0][login]', $row['login']);
		echo htmlspecialchars($row['login']);
	} else {
		echo addInput('data[0][login]', null, 8, 15);
	}
	echo "</td></tr>\n";

?>
<tr><td>Team name:</td><td><?=addInput('data[0][name]', @$row['name'], 35, 255)?></td></tr>
<tr><td>Category:</td><td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT categoryid,name FROM team_category ORDER BY categoryid");
echo addSelect('data[0][categoryid]', $cmap, @$row['categoryid'], true);
?>
</td></tr>
<tr><td>Affiliation:</td><td><?php
$amap = $DB->q("KEYVALUETABLE SELECT affilid,name FROM team_affiliation ORDER BY affilid");
$amap[''] = 'none';
echo addSelect('data[0][affilid]', $amap, @$row['affilid'], true);
?>
</td></tr>
<tr><td>IP address:</td><td><?=addInput('data[0][ipaddress]', @$row['ipaddress'], 35, 32)?></td></tr>
<tr><td>Room:</td><td><?=addInput('data[0][room]', @$row['room'], 10, 15)?></td></tr>
<tr><td valign="top">Comments:</td><td><?=addTextArea('data[0][comments]', @$row['comments'])?></td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','team') .
	addSubmit('Save') .
	addEndForm();

require('../footer.php');
exit;

endif;



if ( ! $id || preg_match('/\W/', $id) ) error("Missing or invalid team id");

/* optional restriction of submissions list to specific problem, language, etc. */
$restrictions = array();
if ( isset($_GET['restrict']) ) {
	list($key, $value) = explode(":",$_GET['restrict'],2);
	$restrictions[$key] = $value;
}

$row = $DB->q('TUPLE SELECT t.*, c.name AS catname, a.name AS affname FROM team t
               LEFT JOIN team_category c USING (categoryid)
               LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
               WHERE login = %s', $id);


echo "<h1>Team ".htmlentities($row['name'])."</h1>\n\n";

?>

<table>
<tr><td>Login:     </td><td class="teamid"><?=$row['login']?></td></tr>
<tr><td>Name:      </td><td><?=htmlentities($row['name'])?></td></tr>
<tr><td>Has passwd:</td><td><?=(isset($row['passwd']) ? 'yes':'no')?></td></tr>
<tr><td>Category:  </td><td><?=(int)$row['categoryid'].
	' - '.htmlentities($row['catname'])?></td></tr>
<?php if (!empty($row['members'])): ?>
<tr><td valign="top">Members:   </td><td><?=
	nl2br(htmlentities($row['members']))?></td></tr>
<?php endif; ?>
<?php if (!empty($row['affilid'])): ?>
<tr><td>Affiliation:</td><td><a href="team_affiliation.php?id=<?=
	urlencode($row['affilid']) . '">' .
	htmlentities($row['affilid'] . ' - ' .
	$row['affname'])?></a></td></tr>
<?php endif; ?>
<tr><td>Host:</td><td><?=@$row['ipaddress'] ? htmlspecialchars($row['ipaddress']).
	' - '.printhost(gethostbyaddr($row['ipaddress']), TRUE):''?></td></tr>
<?php if (!empty($row['room'])): ?>
<tr><td>Room:</td><td><?=htmlentities($row['room'])?></td></tr>
<?php endif; ?>
<?php if (!empty($row['comments'])): ?>
<tr><td valign="top">Comments:</td><td><?=
	nl2br(htmlentities($row['comments']))?></td></tr>
<?php endif; ?>
</table>

<form action="<?=$pagename?>" method="post">
<p>
<input type="hidden" name="id" value="<?=$id?>" />
<input type="hidden" name="cmd" value="rejudge" />
<input type="submit" value="REJUDGE ALL for team <?=$id?>"
 onclick="return confirm('Rejudge all submissions for this team?')" />

<?php

if ( IS_ADMIN ) {
	echo editLink('team', $id). " " . delLink('team','login',$id);
}

echo "</p>\n</form>\n\n";

echo '<h3>Submissions';
if ( isset($key) ) {
	$keystr = "";
	switch ( $key ) {
	case 'probid':    $keystr = "problem";   break;
	case 'langid':    $keystr = "language";  break;
	case 'judgehost': $keystr = "judgehost"; break;
	default:          error("Restriction on $key not allowed.");
	}
	echo ' for ' . htmlspecialchars($keystr) . ': ' . htmlspecialchars($value);
}
echo "</h3>\n\n";

$restrictions['teamid'] = $id;
putSubmissions($restrictions, TRUE);

require('../footer.php');
