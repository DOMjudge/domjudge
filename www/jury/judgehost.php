<?php
/**
 * View judgehost details
 *
 * $Id$
 */

$id = @$_REQUEST['id'];

require('init.php');
$refresh = '15;url='.getBaseURI().'jury/judgehost.php?id='.urlencode($id);
$title = 'Judgehost '.htmlspecialchars(@$id);

if ( ! $id || ! preg_match("/^[A-Za-z0-9_\-.]*$/", $id)) {
	error("Missing or invalid judge hostname");
}

if ( IS_ADMIN && isset($_POST['cmd']) &&
	( $_POST['cmd'] == 'activate' || $_POST['cmd'] == 'deactivate' ) ) {
	$DB->q('UPDATE judgehost SET active = %i WHERE hostname = %s',
	       ($_POST['cmd'] == 'activate' ? 1 : 0), $id);
}

$row = $DB->q('TUPLE SELECT * FROM judgehost WHERE hostname = %s', $id);

require('../header.php');

echo "<h1>Judgehost ".printhost($row['hostname'])."</h1>\n\n";

?>

<table>
<tr><td>Name:  </td><td><?=printhost($row['hostname'], TRUE)?></td></tr>
<tr><td>Active:</td><td><?=printyn($row['active'])?></td></tr>
</table>

<?php
if ( IS_ADMIN ) {
	require_once('../forms.php');

	$cmd = ($row['active'] == 1 ? 'deactivate' : 'activate'); 

	echo addForm('judgehost.php') . "<p>\n" .
		addHidden('id',  $row['hostname']) .
		addHidden('cmd', $cmd) .
		addSubmit($cmd) . "</p>\n" .
		addEndForm();
}

echo rejudgeForm('judgehost', $id);

if ( IS_ADMIN ) {
	echo "<p>" . delLink('judgehost','hostname',$id) . "</p>\n\n";
}

echo "<h3>Judgings by " . printhost($row['hostname']) . "</h3>\n\n";

// get the judgings for a specific key and value pair
// select only specific fields to avoid retrieving large blobs
$res = $DB->q('SELECT judgingid, submitid, starttime, endtime, judgehost,
			   result, verified, valid FROM judging
			   WHERE cid = %i AND judgehost = %s
			   ORDER BY starttime DESC',
			   getCurContest(), $id);

if( $res->count() == 0 ) {
	echo "<p><em>No judgings.</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<thead>\n" .
	     "<tr><th>ID</th><th>start</th><th>end</th>" .
	     "<th>result</th><th>valid</th><th>verified</th>" .
	     "</tr>\n</thead>\n<tbody>\n";

	while( $jud = $res->next() ) {
		$link = 'submission.php?id=' . (int)$jud['submitid'] .
			'&amp;jid=' . (int)$jud['judgingid'];
		echo '<tr' . ( $jud['valid'] ? '' : ' class="disabled"' ) . '>';
		echo '<td><a href="' . $link . '">j' . (int)$jud['judgingid'] .
			'</a></td>';
		echo '<td>' . printtime($jud['starttime']) . '</td>';
		echo '<td>' . printtime(@$jud['endtime'])  . '</td>';
		echo '<td><a href="' . $link . '">' .
			printresult(@$jud['result'], $jud['valid']) . '</a></td>';
		echo '<td align="center">' . printyn($jud['valid']) . '</td>';
		echo '<td align="center">' . printyn($jud['verified']) . '</td>';
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}


require('../footer.php');
