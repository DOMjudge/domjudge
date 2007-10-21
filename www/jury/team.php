<?php
/**
 * View team details
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_REQUEST['id'];

require('init.php');

if ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
} else {
	$refresh = '15;url='.getBaseURI().'jury/'.$pagename.'?id='.urlencode($id).
		(isset($_GET['restrict'])?'&restrict='.urlencode($_GET['restrict']):'');
}

$title = 'Team '.htmlspecialchars(@$id);

require('../header.php');
require('../forms.php');

if ( IS_ADMIN && !empty($cmd) ):
	
	echo "<h2>" . ucfirst($cmd) . " team</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Login:</td><td class=\"teamid\">";
		$row = $DB->q('TUPLE SELECT * FROM team WHERE login = %s',
			$_GET['id']);
		echo addHidden('keydata[0][login]', $row['login']);
		echo htmlspecialchars($row['login']);
	} else {
		echo "<tr><td><label for=\"data_0__login_\">Login:</label></td><td class=\"teamid\">";
		echo addInput('data[0][login]', null, 8, 15);
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__name_">Team name:</label></td>
<td><?=addInput('data[0][name]', @$row['name'], 35, 255)?></td></tr>
<tr><td><label for="data_0__categoryid_">Category:</label></td>
<td><?php
$cmap = $DB->q("KEYVALUETABLE SELECT categoryid,name FROM team_category ORDER BY categoryid");
echo addSelect('data[0][categoryid]', $cmap, @$row['categoryid'], true);
?>
</td></tr>
<tr><td valign="top"><label for="data_0__members_">Members:</label></td>
<td><?=addTextArea('data[0][members]', @$row['members'], 40, 3)?></td></tr>
<tr><td><label for="data_0__affilid_">Affiliation:</label></td>
<td><?php
$amap = $DB->q("KEYVALUETABLE SELECT affilid,name FROM team_affiliation ORDER BY affilid");
$amap[''] = 'none';
echo addSelect('data[0][affilid]', $amap, @$row['affilid'], true);
?>
</td></tr>
<tr><td><label for="data_0__ipaddress_">IP address:</label></td>
<td><?=addInput('data[0][ipaddress]', @$row['ipaddress'], 35, 32)?> (or hostname)</td></tr>
<tr><td><label for="data_0__room_">Room:</label></td>
<td><?=addInput('data[0][room]', @$row['room'], 10, 15)?></td></tr>
<tr><td valign="top"><label for="data_0__comments_">Comments:</label></td>
<td><?=addTextArea('data[0][comments]', @$row['comments'])?></td></tr>
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


echo "<h1>Team ".htmlspecialchars($row['name'])."</h1>\n\n";

?>

<table>
<tr><td scope="row">Login:     </td><td class="teamid"><?=$row['login']?></td></tr>
<tr><td scope="row">Name:      </td><td><?=htmlspecialchars($row['name'])?></td></tr>
<tr><td scope="row">Has passwd:</td><td><?=(isset($row['passwd']) ? 'yes':'no')?>
<?php if ( IS_ADMIN ): ?>
	<a href="genpasswds.php?forteam=<?=$id?>"><img src="../images/edit.png"
	alt="edit" title="set password" class="picto" /></a>
<?php endif; ?>
	</td></tr>
<tr><td scope="row">Category:  </td><td><?=(int)$row['categoryid'].
	' - '.htmlspecialchars($row['catname'])?></td></tr>
<?php if (!empty($row['members'])): ?>
<tr><td valign="top" scope="row">Members:   </td><td><?=
	nl2br(htmlspecialchars($row['members']))?></td></tr>
<?php endif; ?>
<?php if (!empty($row['affilid'])): ?>
<tr><td scope="row">Affiliation:</td><td><a href="team_affiliation.php?id=<?=
	urlencode($row['affilid']) . '">' .
	htmlspecialchars($row['affilid'] . ' - ' .
	$row['affname'])?></a></td></tr>
<?php endif; ?>
<tr><td scope="row">Host:</td><td><?=@$row['ipaddress'] ? htmlspecialchars($row['ipaddress']).
	' - '.printhost(gethostbyaddr($row['ipaddress']), TRUE):'-'?></td></tr>
<?php if (!empty($row['room'])): ?>
<tr><td scope="row">Room:</td><td><?=htmlspecialchars($row['room'])?></td></tr>
<?php endif; ?>
<?php if (!empty($row['comments'])): ?>
<tr><td valign="top" scope="row">Comments:</td><td><?=
	nl2br(htmlspecialchars($row['comments']))?></td></tr>
<?php endif; ?>
</table>


<?php

echo "<br />\n" . rejudgeForm('team', $id) . "\n\n";

if ( IS_ADMIN ) {
	echo "<p>" .
		editLink('team', $id). "\n" .
		delLink('team','login',$id) .
		"</p>\n\n";
}

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
putSubmissions($cdata, $restrictions, TRUE);

require('../footer.php');
