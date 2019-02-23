<?php declare(strict_types=1);
/**
 * Functionality for making printouts from DOMjudge.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Returns boolean indicating whether in-DOMjudge printing
 * has been enabled.
 */
function have_printing() : bool
{
    return (bool) dbconfig_get('enable_printing', 0);
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
    <label for="code">Source file:</label>
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
  <button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-print"></i> Print code</button>
</form>
</div>

<?php
}

function handle_print_upload()
{
    global $DB;

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
 */
function send_print(string $filename, $origname = null, $language = null) : array
{
    global $DB, $username;

    $team = $DB->q('TUPLE SELECT t.name, t.room FROM user u
                        LEFT JOIN team t USING (teamid)
                        WHERE username = %s', $username);
    global $G_SYMFONY;
    $ret = $G_SYMFONY->sendPrint($filename, $origname, $language, $username, $team['name']??'', $team['room']??'');

    return $ret;
}
