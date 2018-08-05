<?php
/**
 * View/edit testcases
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$INOROUT = array('input','output');
$FILES   = array('input','output','image');

$probid = (int)@$_REQUEST['probid'];

$prob = $DB->q('MAYBETUPLE SELECT probid, name
                FROM problem WHERE probid = %i', $probid);

if (! $prob) {
    error("Missing or invalid problem id");
}

function filebase($probid, $rank)
{
    return 'p' . specialchars($probid) . '.t' . $rank . '.';
}

// TODO: check if this duplicates code with the API
function download($probid, $rank, $file)
{
    global $DB, $prob;
    if ($file=='image') {
        $ext = $DB->q('MAYBEVALUE SELECT image_type
                       FROM testcase WHERE probid = %i AND rank = %i',
                      $probid, $rank);
        $type = 'image/' . $ext;
    } else {
        $ext = substr($file, 0, -3);
        $type = 'text/plain';
    }
    $filename = filebase($prob['probid'], $rank) . $ext;

    $size = $DB->q("MAYBEVALUE SELECT OCTET_LENGTH($file)
                    FROM testcase WHERE probid = %i AND rank = %i",
                   $probid, $rank);

    // sanity check before we start to output headers
    if ($size===null || !is_numeric($size)) {
        error("Problem while fetching testcase");
    }

    header("Content-Type: $type; name=\"$filename\"");
    header("Content-Disposition: inline; filename=\"$filename\"");
    header("Content-Length: $size");

    // This may not be good enough for large testsets, but streaming them
    // directly from the database query result seems overkill to implement.
    echo $DB->q("VALUE SELECT SQL_NO_CACHE $file FROM testcase
                 WHERE probid = %i AND rank = %i", $probid, $rank);
}

function reorder_case($rank, $move, $data, $probid)
{
    global $DB;

    // First find testcase to switch with
    $last = null;
    $other = null;
    foreach ($data as $curr => $row) {
        if ($curr==$rank && $move=='up') {
            $other = $last;
            break;
        }
        if ($rank==$last && $move=='down' && $last!==null) {
            $other = $curr;
            break;
        }
        $last = $curr;
    }

    if ($other!==null) {
        // (probid, rank) is a unique key, so we must switch via a
        // temporary rank, and use a transaction.
        $tmprank = 999999;
        $DB->q('START TRANSACTION');
        $DB->q('UPDATE testcase SET rank = %i
                WHERE probid = %i AND rank = %i', $tmprank, $probid, $other);
        $DB->q('UPDATE testcase SET rank = %i
                WHERE probid = %i AND rank = %i', $other, $probid, $rank);
        $DB->q('UPDATE testcase SET rank = %i
                WHERE probid = %i AND rank = %i', $rank, $probid, $tmprank);
        $DB->q('COMMIT');
        auditlog('testcase', $probid, 'switch rank', "$rank <=> $other");
    }
}

function check_updated_file($probid, $rank, $fileid, $file)
{
    global $DB;

    $result = '';
    if (!empty($_FILES[$fileid]['name'][$rank])) {

        // Check for upload errors:
        checkFileUpload($_FILES[$fileid]['error'][$rank]);

        $content = dj_file_get_contents($_FILES[$fileid]['tmp_name'][$rank]);
        if ($DB->q("VALUE SELECT count(testcaseid)
                    FROM testcase WHERE probid = %i AND rank = %i",
                   $probid, $rank)==0) {
            error("cannot find testcase $rank for probid = $probid");
        }

        if ($file=='image') {
            $type = get_image_type($content, $errormsg);
            debug($type, $_FILES[$fileid]);
            if ($type===false) {
                error("image: " . $errormsg);
            }
            $thumb = get_image_thumb($content, $errormsg);
            if ($thumb===false) {
                $thumb = null;
                warning("image: ".$errormsg);
            }

            $DB->q('UPDATE testcase SET image = %s, image_thumb = %s, image_type = %s
                    WHERE probid = %i AND rank = %i',
                   $content, $thumb, $type, $probid, $rank);
        } else {
            $DB->q("UPDATE testcase SET md5sum_$file = %s, $file = %s
                    WHERE probid = %i AND rank = %i",
                   md5($content), $content, $probid, $rank);
        }

        auditlog('testcase', $probid, 'updated', "$file rank $rank");

        $result .= "<li>Updated $file for testcase $rank with file " .
            specialchars($_FILES[$fileid]['name'][$rank]) .
            " (" . printsize($_FILES[$fileid]['size'][$rank]) . ")";
        if ($file=='output' &&
            $_FILES[$fileid]['size'][$rank]>dbconfig_get('output_limit')*1024) {
            $result .= ".<br /><b>Warning: file size exceeds " .
                "<code>output_limit</code> of " . dbconfig_get('output_limit') .
                " kB. This will always result in wrong answers!</b>";
        }
        $result .= "</li>\n";
    }

    return $result;
}

function check_update($probid, $rank, $FILES)
{
    global $DB;
    $result = '';
    foreach ($FILES as $file) {
        $fileid = 'update_'.$file;
        $result .= check_updated_file($probid, $rank, $fileid, $file);
    }

    // check for updated sample
    $affected = $DB->q('RETURNAFFECTED UPDATE testcase SET sample = %i
                        WHERE probid = %i AND rank = %i',
                       isset($_POST['sample'][$rank]), $probid, $rank);
    if ($affected) {
        $result .= "<li>Set testcase $rank to be " .
               (isset($_POST['sample'][$rank]) ? "" : "not ") .
               "a sample testcase</li>\n";
    }

    // check for updated description
    if (isset($_POST['description'][$rank])) {
        $DB->q('UPDATE testcase SET description = %s
                WHERE probid = %i AND rank = %i',
               $_POST['description'][$rank], $probid, $rank);
        auditlog('testcase', $probid, 'updated description', "rank $rank");

        $result .= "<li>Updated description for testcase $rank</li>\n";
    }

    return $result;
}

function check_add($probid, $rank, $FILES)
{
    global $DB;

    $result = '';
    if (!empty($_FILES['add_input']['name']) ||
        !empty($_FILES['add_output']['name'])) {
        $content = array();
        foreach ($FILES as $file) {
            if (!empty($_FILES['add_'.$file]['name'])) {
                checkFileUpload($_FILES['add_'.$file]['error']);
                $content[$file] = dj_file_get_contents($_FILES['add_'.$file]['tmp_name']);
            }
        }

        $DB->q("INSERT INTO testcase
                (probid,rank,md5sum_input,md5sum_output,input,output,description,sample)
                VALUES (%i,%i,%s,%s,%s,%s,%s,%i)",
               $probid, $rank, md5(@$content['input']), md5(@$content['output']),
               @$content['input'], @$content['output'], @$_POST['add_desc'],
               isset($_POST['add_sample']));

        if (!empty($content['image'])) {
            list($thumb, $type) = get_image_thumb_and_type($content['image']);

            $DB->q('UPDATE testcase SET image = %s, image_thumb = %s, image_type = %s
                    WHERE probid = %i AND rank = %i',
                   @$content['image'], $thumb, $type, $probid, $rank);
        }

        auditlog('testcase', $probid, 'added', "rank $rank");

        $result .= "<li>Added new testcase $rank from files " .
            specialchars($_FILES['add_input']['name']) .
            " (" . printsize($_FILES['add_input']['size']) . ") and " .
            specialchars($_FILES['add_output']['name']) .
            " (" . printsize($_FILES['add_output']['size']) . ").";
        if ($_FILES['add_output']['size']>dbconfig_get('output_limit')*1024) {
            $result .= "<br /><b>Warning: output file size exceeds " .
                "<code>output_limit</code> of " . dbconfig_get('output_limit') .
                " kB. This will always result in wrong answers!</b>";
        }
        if (empty($content['input']) || empty($content['output'])) {
            $result .= "<br /><b>Warning: empty testcase file(s)!</b>";
        }
        $result .= "</li>\n";
    }

    return $result;
}

// We may need to re-update the testcase data, so make it a function.
function read_testdata($probid)
{
    global $DB;
    return $DB->q('KEYTABLE SELECT rank AS ARRAYKEY, testcaseid, rank,
                   description, sample, image_type,
                   OCTET_LENGTH(input)  AS size_input,  md5sum_input,
                   OCTET_LENGTH(output) AS size_output, md5sum_output,
                   OCTET_LENGTH(image)  AS size_image
                   FROM testcase WHERE probid = %i ORDER BY rank', $probid);
}

// Download testcase
if (isset($_GET['fetch']) && in_array($_GET['fetch'], $FILES)) {
    download($probid, $_GET['rank'], $_GET['fetch']);
    exit(0);
}

$data = read_testdata($probid);

// Reorder testcases
if (isset($_GET['move'])) {
    reorder_case($_GET['rank'], $_GET['move'], $data, $probid);
    // Redirect to the original page to prevent accidental redo's
    header('Location: testcase.php?probid=' . urlencode($probid));
    return;
}

$title = 'Testcases for problem p'.specialchars(@$probid).' - '.specialchars($prob['name']);

$result = '';
if (isset($_POST['probid']) && IS_ADMIN) {
    $maxrank = 0;
    foreach ($data as $rank => $row) {
        $result .= check_update($probid, $rank, $FILES);
        if ($rank>$maxrank) {
            $maxrank = $rank;
        }
    }

    $result .= check_add($probid, $maxrank + 1, $FILES);
}
if (!empty($result)) {
    // Reload testcase data after updates
    $data = read_testdata($probid);
}

// Check if ranks must be renumbered (if test cases have been deleted).
// There is no need to run this within one MySQL transaction since
// nothing depends on the ranks being sequential, and we do preserve
// their order while renumbering.
end($data);
if (count($data)<(int)key($data)) {
    $newrank = 1;
    foreach ($data as $rank => $row) {
        $DB->q('UPDATE testcase SET rank = %i
                WHERE probid = %i AND rank = %i', $newrank++, $probid, $rank);
    }

    $result .= "<li>Test case rankings reordered.</li>\n";

    // Reload testcase data after updates
    $data = read_testdata($probid);
}

renderPage(array(
    'title' => $title,
    'testdata' => $data,
    'probid' => $probid,
    'is_admin' => IS_ADMIN,
    'result' => $result
));
