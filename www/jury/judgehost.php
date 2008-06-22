<?php
/**
 * View judgehost details
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
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

require(SYSTEM_ROOT . '/lib/www/header.php');

echo "<h1>Judgehost ".printhost($row['hostname'])."</h1>\n\n";

?>

<table>
<tr><td scope="row">Name:  </td><td><?=printhost($row['hostname'], TRUE)?></td></tr>
<tr><td scope="row">Active:</td><td><?=printyn($row['active'])?></td></tr>
<tr><td scope="row">Status:</td><td>
<?php
if ( empty($row['polltime']) ) {
	echo "Judgehost never checked in.";
} else {
	$reltime = time() - strtotime($row['polltime']);
	if ( $reltime < 30 ) {
		echo "OK";
	} else if ( $reltime < 120 ) {
		echo "Warning";
	} else {
		echo "Error";
	}
	echo ", judgehost last checked in ". $reltime . " seconds ago.";
}
?>
</td></tr>
</table>

<?php
if ( IS_ADMIN ) {
	require_once(SYSTEM_ROOT . '/lib/www/forms.php');

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
			   ORDER BY starttime DESC, judgingid DESC',
			   $cid, $id);

if( $res->count() == 0 ) {
	echo "<p><em>No judgings.</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n<thead>\n" .
	     "<tr><th scope=\"col\">ID</th><th scope=\"col\">start</th>" .
		 "<th scope=\"col\">end</th><th scope=\"col\">result</th>" .
		 "<th scope=\"col\">valid</th><th scope=\"col\">verified</th>" .
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
			printresult(@$jud['result'], $jud['valid'], TRUE) . '</a></td>';
		echo '<td align="center">' . printyn($jud['valid']) . '</td>';
		echo '<td align="center">' . printyn($jud['verified']) . '</td>';
		echo "</tr>\n";
	}
	echo "</tbody>\n</table>\n\n";
}


require(SYSTEM_ROOT . '/lib/www/footer.php');
