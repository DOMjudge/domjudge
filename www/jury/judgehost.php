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

putJudgings('judgehost', $row['hostname']);

require('../footer.php');
