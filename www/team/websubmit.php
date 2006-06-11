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

$cid = getCurContest();

if ( $cid === FALSE  ) {
	echo "<p><b>No contest defined!</b></p>\n";
	require('../footer.php');
	exit;
}

?><h1>New Submission</h1>

<form action="upload.php" method="post" enctype="multipart/form-data">

<table class="websubmit">
<tr><td>Problem:</td>
    <td><?

$probs = $DB->q('SELECT probid, name FROM problem
                 WHERE cid = %i AND allow_submit = 1
                 ORDER BY probid', $cid);

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
<tr><td>Language:</td>
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
<tr><td>File:</td>
    <td><input type="file" name="code" size="40" /></td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr><td></td>
    <td><input type="submit" value="Submit solution" name="submit" /></td>
</tr>
</table>

</form>

<?
require('../footer.php');
