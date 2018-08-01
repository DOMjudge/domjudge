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
    return dbconfig_get('enable_printing', 0);
}

function put_print_form()
{
    global $DB, $pagename;

    $langs = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name, extensions, require_entry_point, entry_point_description FROM language
                     WHERE allow_submit = 1 ORDER BY name');
    $langlist = array();
    $langlist[''] = 'plain text';
    foreach ($langs as $langid => $langdata) {
        $langlist[$langid] = $langdata['name'];
    } ?>

    <script type="text/javascript">
    function detectLanguage(filename)
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

    }

    <?php
    putgetMainExtension($langs); ?>
    </script>

<div class="container submitform">
<form action="<?=specialchars($pagename)?>" method="post" enctype="multipart/form-data">

  <div class="form-group">
    <label for="maincode">Source file:</label>
    <input type="file" class="form-control-file" name="code" id="code" required onchange='detectLanguage(document.getElementById("code").value);' />
  </div>
  <div class="form-group">
    <label for="langid">Language:</label>
    <select class="custom-select" name="langid" id="langid">


<?php
    foreach ($langlist as $langid => $langname) {
        print '      <option value="' .specialchars($langid). '">' . specialchars($langname) . "</option>\n";
    } ?>
    </select>
  </div>
  <input type="submit" name="submit" value="Print code" class="btn btn-primary" />
</form>
</div>

<?php
}

function handle_print_upload()
{
    global $DB;

    ini_set("upload_max_filesize", dbconfig_get('sourcesize_limit') * 1024);

    checkFileUpload($_FILES['code']['error']);

    $filename = $_FILES['code']['name'];
    $realfilename = $_FILES['code']['tmp_name'];

    /* Determine the language */
    $langid = @$_POST['langid'];
    /* sanity check only */
    if ($langid != "") {
        $lang = $DB->q('MAYBETUPLE SELECT langid FROM language
                        WHERE langid = %s AND allow_submit = 1', $langid);

        if (! isset($lang)) {
            error("Unable to find language '$langid'");
        }
    }

    $ret = send_print($realfilename, $filename, $langid);

    echo "<div>" . nl2br(specialchars($ret[1])) . "</div>\n\n";

    if ($ret[0]) {
        echo "<div class=\"alert alert-success\">Print successful.</div>";
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
 *   $origname: the original filename as submitted by the team
 *   $language: langid of the programming language this file is in
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
function send_print($filename, $origname = null, $language = null)
{
    global $DB, $username;

    // Map our language to enscript language:
    $lang_remap = array(
        'adb'    => 'ada',
        'bash'   => 'sh',
        'csharp' => 'c',
        'f95'    => 'f90',
        'hs'     => 'haskell',
        'js'     => 'javascript',
        'pas'    => 'pascal',
        'pl'     => 'perl',
        'py'     => 'python',
        'py2'    => 'python',
        'py3'    => 'python',
        'rb'     => 'ruby',
    );
    if (isset($language) && array_key_exists($language, $lang_remap)) {
        $language = $lang_remap[$language];
    }
    switch ($language) {
    case 'csharp': $language = 'c'; break;
    case 'hs': $language = 'haskell'; break;
    case 'pas': $language = 'pascal'; break;
    case 'pl': $language = 'perl'; break;
    case 'py':
    case 'py2':
    case 'py3':
        $language = 'python'; break;
    }
    $highlight = "";
    if (! empty($language)) {
        $highlight = "-E" . escapeshellarg($language);
    }

    $team = $DB->q('TUPLE SELECT t.name, t.room FROM user u
                        LEFT JOIN team t USING (teamid)
                        WHERE username = %s', $username);
    $header = "Team: $username " . $team['name'] .
              (!empty($team['room']) ? "[".$team['room']."]":"") .
              " File: $origname||Page $% of $=";

    // For debugging or spooling to a different host.
    // Also uncomment '-p $tmp' below.
    //$tmp = tempnam(TMPDIR, 'print_'.$username.'_');

    $cmd = "enscript -C " . $highlight
         . " -b " . escapeshellarg($header)
         . " -a 0-10 "
         . " -f Courier9 "
         //. " -p $tmp "
         . escapeshellarg($filename) . " 2>&1";

    exec($cmd, $output, $retval);

    // Make file readable for others than webserver user,
    // and give it an extension:
    //chmod($tmp, 0644);
    //rename($tmp, $tmp.'.ps');

    return array($retval == 0, implode("\n", $output));
}
