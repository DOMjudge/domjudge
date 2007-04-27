<?php
/**
 * Add a new judgehost
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');
$title = 'New judgehost';
requireAdmin();

$id = @$_POST['id'];
if($id && ! preg_match("/^[A-Za-z0-9_\-.]*$/", $id)) {
	$errors = "invalid judge hostname";
	unset($id);
}

if(isset($id)) {
	// TODO: graceful handling of insert failure
	$DB->q('INSERT INTO judgehost (hostname, active) VALUES (%s , %i)'
		, $id
		, (isset($_POST['active']) && $_POST['active'] == 1 ? 1 : 0 )
		);
	if(!isset($_POST['another'])) {
		header('Location: ' . getBaseURI() . 'jury/judgehosts.php');
		exit;
	}
}


require('../header.php');

if(isset($errors)) {
	echo "<div class=\"error\">$errors</div>\n\n";
}

echo "<h1>New judgehost</h1>\n\n";

?>

<form action="judgehost-new.php" method="post">
<table>
<tr><td>Name:  </td>
    <td>
	<input name="id" type="text">
    </td>
</tr>
<tr>
	<td>Active:</td>
	<td>
	<select name="active">
	<option selected value="1">Yes</option>
	<option value="0">No</option>
	</select>
	</td>
</tr>
<tr>
	<td></td>
	<td><input type="checkbox" name="another" value="more"
		<?=isset($_POST['another'])?'checked="checked"':''?>/>
		add another judgehost
	</td>
</tr>
</table>

<input type="submit" value="Add judgehost" />

</form>

<?php

require('../footer.php');
