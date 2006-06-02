<?php
/**
 * Web submissions form
 *
 * $Id$
 */

require('init.php');

$title = 'Websubmit';
require('../header.php');
require('menu.php');

?><h1>New Submission</h1>

<form action="upload.php" method="post" enctype="multipart/form-data">

<table class="websubmit">
<tr><td>problem:</td>
    <td><?

$probs = $DB->q('SELECT probid, name FROM problem
                 WHERE cid = %i AND allow_submit = 1
                 ORDER BY probid', getCurContest());

if( $probs->count() == 0 ) {
	error('No problems defined for this contest');
}

echo '<select name="probid">'."\n";
echo '<option value="">by filename</option>'."\n";
while( $row = $probs->next() ) {
	echo '<option value="' . $row['probid'] . '">'
		. $row['name'] . '</option>'."\n";
}
echo "</select>";

?></td>
</tr>
<tr><td>language:</td>
    <td><?

$langs = $DB->q('SELECT extension, name FROM language
                 WHERE allow_submit = 1 ORDER BY name');

if( $langs->count() == 0 ) {
	error('No languages defined');
}

echo '<select name="langext">';
echo '<option value="">by extension</option>'."\n";
while( $row = $langs->next() ) {
	echo '<option value="' . $row['extension'] . '">'
		. $row['name'] . '</option>'."\n";
}
echo "</select>";

?></td>
</tr>
<tr><td>file:</td>
    <td><input type="file" name="code" /></td>
</tr>
<tr><td></td>
    <td><input type="submit" value="Submit" name="submit" /></td>
</tr>
</table>

</form>

<?
require('../footer.php');
