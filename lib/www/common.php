<?php declare(strict_types=1);
/**
 * Common functions shared between team/public/jury interface
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

/** Text symbol used in output to represent a circle */
define('CIRCLE_SYM', '&#9679;');

/**
 * Output progress bar
 */
function putProgressBar(int $margin = 0)
{
    global $cdata;

    $fdata = calcFreezeData($cdata);
    if ($cdata === null) {
        return;
    }
    $left = difftime((float)$cdata['endtime'], now());
    if (!$fdata['started'] || $left < 0) {
        return;
    }
    $passed = difftime((float)$cdata['starttime'], now());
    $duration = difftime((float)$cdata['starttime'], (float)$cdata['endtime']);
    $percent = (int)($passed*100./$duration);
    print '
<div class="progress" style="margin-top: ' . $margin . 'px; height: 10px;">
  <div class="progress-bar" role="progressbar" style="width: ' . $percent . '%;"
       aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"></div>
</div>
';
}

/**
 * Translate error codes from PHP's file upload function into
 * concrete error strings.
 */
function checkFileUpload(int $errorcode)
{
    switch ($errorcode) {
        case UPLOAD_ERR_OK: // everything ok!
            return;
        case UPLOAD_ERR_INI_SIZE:
            error('The uploaded file is too large (exceeds the upload_max_filesize directive).');
            // no break
        case UPLOAD_ERR_FORM_SIZE:
            error('The uploaded file is too large (exceeds the MAX_FILE_SIZE directive).');
            // no break
        case UPLOAD_ERR_PARTIAL:
            error('The uploaded file was only partially uploaded.');
            // no break
        case UPLOAD_ERR_NO_FILE:
            error('No file was uploaded.');
            // no break
        case UPLOAD_ERR_NO_TMP_DIR:
            error('Missing a temporary folder. Contact staff.');
            // no break
        case UPLOAD_ERR_CANT_WRITE:
            error('Failed to write file to disk. Contact staff.');
            // no break
        case UPLOAD_ERR_EXTENSION:
            error('File upload stopped by extension. Contact staff.');
            // no break
        default:
            error('Unknown error while uploading: '. $_FILES['code']['error'] .
                '. Contact staff.');
    }
}

/**
 * Output JavaScript function that contains the language extensions as
 * configured in the database so the frontend can use them to automatically
 * detect the language from the filename extension.
 * Also output a function that returns the entry point description for
 * a language if an entry point is required.
 */
function putgetMainExtension(array $langdata)
{
    echo "function getMainExtension(ext)\n{\n";
    echo "\tswitch(ext) {\n";
    foreach ($langdata as $langid => $data) {
        $exts = dj_json_decode($data['extensions']);
        if (!is_array($exts)) {
            continue;
        }
        foreach ($exts as $ext) {
            echo "\t\tcase '" . $ext . "': return '" . $langid . "';\n";
        }
    }
    echo "\t\tdefault: return '';\n\t}\n}\n\n";
    echo "function getEntryPoints(mainext)\n{\n";
    echo "\tswitch(mainext) {\n";
    foreach ($langdata as $langid => $data) {
        if (!$data['require_entry_point']) {
            continue;
        }
        $desc = $data['entry_point_description'] ?: "Entry point";
        echo "\t\tcase '" . $langid . "': return '" . $desc . "';\n";
    }
    echo "\t\tdefault: return '';\n\t}\n}\n\n";
}
