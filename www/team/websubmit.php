<?php
/**
 * Web submissions form
 *
 * $Id$
 */

require('init.php');

if ( ! ENABLEWEBSUBMIT ) {
	error("Websubmit disabled!");
}


$title = 'Submit';
require('../header.php');
require('menu.php');

$cid = getCurContest();

if ( $cid === FALSE  ) {
	echo "<p><em>No contests defined!</em></p>\n";
	require('../footer.php');
	exit;
}

// Put overview of team submissions (like scoreboard)
echo "<div id=\"teamscoresummary\">\n";
putTeamRow($login);
echo "</div>\n";

?><h1>New Submission</h1>

<form action="upload.php" method="post" enctype="multipart/form-data">

<table>
<tr><td><label for="probid">Problem</label>:</td>
    <td><?php

$probs = $DB->q('SELECT probid, name FROM problem
                 WHERE cid = %i AND allow_submit = 1
                 ORDER BY probid', $cid);

if( $probs->count() == 0 ) {
	error('No problems defined for this contest');
}

echo '<select name="probid" id="probid">'."\n";
echo '<option value="">by filename</option>'."\n";
while( $row = $probs->next() ) {
	echo '<option value="' . htmlspecialchars($row['probid']) . '">'
		. htmlentities($row['probid'].': ' .$row['name']) . '</option>'."\n";
}
echo "</select>";

?></td>
</tr>
<tr><td><label for="langext">Language</label>:</td>
    <td><?php

$langs = $DB->q('SELECT extension, name FROM language
                 WHERE allow_submit = 1 ORDER BY name');

if( $langs->count() == 0 ) {
	error('No languages defined');
}

echo '<select name="langext" id="langext">';
echo '<option value="">by extension</option>'."\n";
while( $row = $langs->next() ) {
	echo '<option value="' . htmlspecialchars($row['extension']) . '">'
		. htmlentities($row['name']) . '</option>'."\n";
}
echo "</select>";

?></td>
</tr>
<tr><td><label for="code">File</label>:</td>
    <td><input type="file" name="code" id="code" size="40" /></td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr><td></td>
    <td><input type="submit" value="Submit solution" name="submit"
               onclick="return confirm('Make submission?')" /></td>
</tr>
</table>

</form>

<?php
require('../footer.php');
