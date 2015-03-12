<?php
/**
 * Functionality for making printouts from DOMjudge.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Returns boolean indicating whether in-DOMjudge printing
 * has been enabled.
 */
function have_printing()
{
	return dbconfig_get('enable_printing',0);
}

function put_print_form()
{
	global $DB, $pagename;

	$langs = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name, extensions FROM language
	                 WHERE allow_submit = 1 ORDER BY name');
	echo "<script type=\"text/javascript\">\n<!--\n";
	echo "function detectLanguage(filename)
	{
		var parts = filename.toLowerCase().split('.').reverse();
		if ( parts.length < 2 ) return;

		// language ID

		var elt=document.getElementById('langid');
		// the 'autodetect' option has empty value
		if ( elt.value != '' ) return;

		var langid = getMainExtension(parts[0]);
		for (i=0;i<elt.length;i++) {
			if ( elt.options[i].value == langid ) {
				elt.selectedIndex = i;
			}
		}

	}\n";

	putgetMainExtension($langs);

	echo "// -->\n</script>\n";

	echo addForm($pagename,'post',null,'multipart/form-data');

	?>

	<table>
	<tr><td><label for="code">File</label>:</td>
	<td><input type="file" name="code" id="code" size="40" required onChange='detectLanguage(document.getElementById("code").value);' /></td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td><label for="langid">Language</label>:</td>
	    <td><?php

	$langlist = array();
	foreach($langs as $langid => $langdata) {
		$langlist[$langid] = $langdata['name'];
	}
	$langlist[''] = 'plain text';
	echo addSelect('langid', $langlist, '', true);

	?></td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td></td>
	    <td><?php echo addSubmit('Print code', 'submit'); ?></td>
	</tr>
	</table>

	<?php

	echo addEndForm();
}

function handle_print_upload()
{
	global $DB, $username;

	ini_set("upload_max_filesize", dbconfig_get('sourcesize_limit') * 1024);

	checkFileUpload($_FILES['code']['error']);

	$filename = $_FILES['code']['name'];
	$realfilename = $_FILES['code']['tmp_name'];

	/* Determine the language */
	$langid = @$_POST['langid'];
	/* sanity check only */
	if ( $langid != "" ) {
		$lang = $DB->q('MAYBETUPLE SELECT langid FROM language
		                WHERE langid = %s AND allow_submit = 1', $langid);

		if ( ! isset($lang) ) error("Unable to find language '$langid'");
	}

	if ( IS_JURY ) $whoami = 'JURY/' . $username;
	else $whoami = $username;

	$ret = send_print($realfilename,$langid,$whoami,$filename);

	echo "<p>" . nl2br(htmlspecialchars($ret[1])) . "</p>\n\n";

	if ( $ret[0] ) {
		echo "<p>Print successful.</p>";
	} else {
		error("Error while printing. Contact staff.");
	}
}

/**
 * Function to send a local file to the printer.
 * Change this to match your local setup.
 *
 * The following parameters are available. Make sure you escape
 * them correctly before passing them to the shell.
 *   $filename: the on-disk file to be printed out
 *   $language: langid of the programming language this file is in
 *   $team: the originating team
 *   $origname: the original filename as submitted by the team
 *
 * Returns array with two elements: first a boolean indicating
 * overall success, and second a string to be displayed to the user.
 *
 * The default configuration of this function depends on the enscript
 * tool. It will optionally format the incoming text for the
 * specified language, and adds a header line with the team ID for
 * easy identification. To prevent misuse the amount of pages per
 * job is limited to 10.
 */
function send_print($filename, $language = null, $team = null, $origname = null)
{
	switch ($language) {
	case 'csharp': $language = 'c'; break;
	case 'hs': $language = 'haskell'; break;
	case 'pas': $language = 'pascal'; break;
	case 'pl': $language = 'perl'; break;
	case 'py': $language = 'python'; break;
	}
	$highlight = "";
	if ( ! empty($language) ) {
		$highlight = "-E" . escapeshellarg($language);
	}

	$banner = "TeamID: $team     File: $origname    Page $% of $= ";

	$cmd = "enscript -C " . $highlight
	     . " -b " . escapeshellarg($banner)
	     . " -a 0-10 "
	// for debugging: uncomment next line
	//   . " -p /tmp/test.ps "
	     . escapeshellarg($filename) . " 2>&1";

	exec($cmd, $output, $retval);

	return array($retval == 0, implode("\n", $output));
}
