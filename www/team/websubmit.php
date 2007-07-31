<?php
/**
 * Web submissions form
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

if ( ! ENABLE_WEBSUBMIT_SERVER ) {
	error("Websubmit disabled.");
}

$title = 'Submit';
require('../header.php');
require('../forms.php');

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

echo "<h1>New Submission</h1>\n\n";

echo addForm('upload.php','post',null,'multipart/form-data');

?>
<table>
<tr><td><label for="probid">Problem</label>:</td>
    <td><?php

$probs = $DB->q('KEYVALUETABLE SELECT probid, CONCAT(probid,": ",name) as name FROM problem
                 WHERE cid = %i AND allow_submit = 1
                 ORDER BY probid', $cid);

if( count($probs) == 0 ) {
	error('No problems defined for this contest');
}
$probs = array_merge(array(''=>'by filename'), $probs);

echo addSelect('probid', $probs, '', true);

?></td>
</tr>
<tr><td><label for="langext">Language</label>:</td>
    <td><?php

$langs = $DB->q('KEYVALUETABLE SELECT extension, name FROM language
                 WHERE allow_submit = 1 ORDER BY name');

if( count($langs) == 0 ) {
	error('No languages defined');
}

$langs = array_merge(array(''=>'by extension'), $langs);
echo addSelect('langext', $langs, '', true);

?></td>
</tr>
<tr><td><label for="code">File</label>:</td>
    <td><?php echo addFileField('code', 40); ?></td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr><td></td>
    <td><?php echo addSubmit('Submit solution', 'submit',
               "return confirm('Make submission?')"); ?></td>
</tr>
</table>

<?php

echo addEndForm();

require('../footer.php');
