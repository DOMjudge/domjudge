<?php declare(strict_types=1);

/**
 * Common functions in jury interface
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once(LIBVENDORDIR . '/autoload.php');

/**
 * Return a link to add a new row to a specific table.
 */
function addLink(string $table, bool $multi = false) : string
{
    return "<a href=\"" . specialchars($table) . ".php?cmd=add\">" .
        "<img src=\"../images/add" . ($multi?"-multi":"") .
        ".png\" alt=\"add" . ($multi?" multiple":"") .
        "\" title=\"add" .   ($multi?" multiple":"") .
        " new " . specialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to edit a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 * Includes a referrer field, which notes the page on which this function
 * was called, so edit.php can return us back here.
 */
function editLink(string $table, $value, bool $multi = false) : string
{
    return "<a href=\"" . specialchars($table) . ".php?cmd=edit" .
        ($multi ? "" : "&amp;id=" . urlencode((string)$value)) .
        "&amp;referrer=" . urlencode(basename($_SERVER['SCRIPT_NAME']) .
        (empty($_REQUEST['id']) ? '' : '?id=' . urlencode((string)$_REQUEST['id']))) .
        "\">" .
        "<img src=\"../images/edit" . ($multi?"-multi":"") .
        ".png\" alt=\"edit" . ($multi?" multiple":"") .
        "\" title=\"edit " .   ($multi?"multiple ":"this ") .
        specialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Returns a link to export a problem as zip-file.
 *
 */
function exportProblemLink($probid) : string
{
    return '<a href="problems/' . urlencode((string)$probid) .
        '/export"><img src="../images/b_save.png" ' .
        ' title="export problem as zip-file" alt="export" /></a>';
}

/**
 * Returns a form to rejudge all judgings based on a (table,id)
 * pair. For example, to rejudge all for language 'java', call
 * as rejudgeForm('language', 'java').
 */
function rejudgeForm(string $table, $id) : string
{
    $ret = '<div id="rejudge" class="framed">' .
         addForm('rejudge/') .
         addHidden('table', $table) .
         addHidden('id', (string)$id);

    $button = 'REJUDGE this submission';
    $question = "Rejudge submission s$id?";
    $disabled = false;
    $allbutton = false;

    // special case submission
    if ($table == 'submission') {

        // disable the form button if there are no valid judgings anyway
        // (nothing to rejudge) or if the result is already correct
        global $DB;
        $validresult = $DB->q('MAYBEVALUE SELECT result FROM judging WHERE
                               submitid = %i AND valid = 1', $id);

        if (IS_ADMIN) {
            if (! $validresult) {
                $question = "Restart judging of PENDING submission s$id, are you sure?";
                $button = 'RESTART judging';
            } elseif ($validresult == 'correct') {
                $question = "Rejudge CORRECT submission s$id, are you sure?";
            }
        } else {
            if (! $validresult || $validresult == 'correct') {
                $disabled = true;
            }
        }
    } else {
        $button = "REJUDGE ALL for $table $id";
        $question = "Rejudge all submissions for this $table?";
        if (IS_ADMIN) {
            $allbutton = true;
        }
    }

    $ret .= '<input type="submit" value="' . specialchars($button) . '" ' .
        ($disabled ? 'disabled="disabled"' : 'onclick="return confirm(\'' .
        specialchars($question) . '\');"') . " /><br />\n" .
        ($allbutton ? addCheckBox('include_all') .
                      '<label for="include_all">include pending/correct submissions</label><br />' : '') .
        addCheckBox('full_rejudge') . '<label for="full_rejudge">create rejudging with reason: </label>' .
        addInput('reason', '', 0, 255) .
        addEndForm() . '</div>';

    return $ret;
}


/**
 * Returns TRUE iff string $haystack starts with string $needle
 */
function starts_with(string $haystack, string $needle) : bool
{
    return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
}
/**
 * Returns TRUE iff string $haystack ends with string $needle
 */
function ends_with(string $haystack, string $needle) : bool
{
    return mb_substr($haystack, mb_strlen($haystack)-mb_strlen($needle)) === $needle;
}

/**
 * tries to open corresponding zip archive
 */
function openZipFile(string $filename) : ZipArchive
{
    $zip = new ZipArchive;
    $res = $zip->open($filename, ZIPARCHIVE::CHECKCONS);
    if ($res === ZIPARCHIVE::ER_NOZIP || $res === ZIPARCHIVE::ER_INCONS) {
        error("no valid zip archive given");
    } elseif ($res === ZIPARCHIVE::ER_MEMORY) {
        error("not enough memory to extract zip archive");
    } elseif ($res !== true) {
        error("unknown error while extracting zip archive");
    }

    return $zip;
}

/**
 * Detects mime-type (the part after 'image/') from image contents.
 * Returns FALSE on errors and stores error message in $error if set.
 */
function get_image_type($image, &$error)
{
    if (!function_exists('gd_info')) {
        $error = "Cannot import image: the PHP GD library is missing.";
        return false;
    }

    $info = getimagesizefromstring($image);
    if ($info === false) {
        $error = "Could not determine image information.";
        return false;
    }

    $type = image_type_to_extension($info[2], false);

    if (!in_array($type, array('jpeg', 'png', 'gif'))) {
        $error = "Unsupported image type '$type' found.";
        return false;
    }

    return $type;
}

/**
 * Generate resized thumbnail image and return as as string.
 * Return FALSE on errors and stores error message in $error if set.
 */
function get_image_thumb($image, &$error)
{
    if (!function_exists('gd_info')) {
        $error = "Cannot import image: the PHP GD library is missing.";
        return false;
    }

    $type = get_image_type($image, $error);
    if ($type===false) {
        $error = "Could not determine image information.";
        return false;
    }

    $info = getimagesizefromstring($image);

    $thumbmaxsize = dbconfig_get('thumbnail_size', 128);

    $rescale = $thumbmaxsize / max($info[0], $info[1]);
    $thumbsize = array((int)round($info[0]*$rescale),
                       (int)round($info[1]*$rescale));

    $orig = imagecreatefromstring($image);
    $thumb = imagecreatetruecolor($thumbsize[0], $thumbsize[1]);
    if ($orig===false || $thumb===false) {
        $error = 'Cannot create GD image.';
        return false;
    }

    if (!imagecopyresampled($thumb, $orig, 0, 0, 0, 0,
                            $thumbsize[0], $thumbsize[1], $info[0], $info[1])) {
        $error = 'Cannot create resized thumbnail image.';
        return false;
    }

    if (!($tmpfname = tempnam(TMPDIR, "thumb-"))) {
        $error = 'Cannot create temporary file in directory ' . TMPDIR . '.';
        return false;
    }

    $success = false;
    switch ($type) {
    case 'jpeg': $success = imagejpeg($thumb, $tmpfname); break;
    case 'png':  $success = imagepng($thumb, $tmpfname); break;
    case 'gif':  $success = imagegif($thumb, $tmpfname); break;
    }
    if (!$success) {
        $error = 'Failed to output thumbnail image.';
        return false;
    }
    if (($thumbstr = file_get_contents($tmpfname))===false) {
        $error = "Cannot read image from temporary file '$tmpfname'.";
        return false;
    }

    imagedestroy($orig);
    imagedestroy($thumb);

    return $thumbstr;
}
