<?php

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
function addLink($table, $multi = false)
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
function editLink($table, $value, $multi = false)
{
    return "<a href=\"" . specialchars($table) . ".php?cmd=edit" .
        ($multi ? "" : "&amp;id=" . urlencode($value)) .
        "&amp;referrer=" . urlencode(basename($_SERVER['SCRIPT_NAME']) .
        (empty($_REQUEST['id']) ? '' : '?id=' . urlencode($_REQUEST['id']))) .
        "\">" .
        "<img src=\"../images/edit" . ($multi?"-multi":"") .
        ".png\" alt=\"edit" . ($multi?" multiple":"") .
        "\" title=\"edit " .   ($multi?"multiple ":"this ") .
        specialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to delete a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 * Optionally specify an extra description of the item to be deleted.
 */
function delLink($table, $field, $value, $desc = null)
{
    return delLinkMultiple($table, array($field), array($value), '', $desc);
}

/**
 * Return a link to delete a specific data element from a given table.
 * Takes the table, the key fields to match on and the values.
 */
function delLinkMultiple($table, $fields, $values, $referrer = '', $desc = null)
{
    $arguments = '';
    foreach ($fields as $i => $field) {
        $arguments .= '&amp;' . $field . '=' . urlencode($values[$i]);
    }
    return "<a href=\"delete.php?table=" . urlencode($table) . $arguments .
           "&amp;referrer=" . urlencode($referrer) .
           (isset($desc) ? "&amp;desc=".urlencode($desc)  : '') .
           "\"><img src=\"../images/delete.png\" " .
           "alt=\"delete\" title=\"delete this " . specialchars($table) .
           "\" class=\"picto\" /></a>";
}

/**
 * Returns a link to export a problem as zip-file.
 *
 */
function exportProblemLink($probid)
{
    return '<a href="export_problem.php?id=' . urlencode($probid) .
        '"><img src="../images/b_save.png" ' .
        ' title="export problem as zip-file" alt="export" /></a>';
}

/**
 * Returns a form to rejudge all judgings based on a (table,id)
 * pair. For example, to rejudge all for language 'java', call
 * as rejudgeForm('language', 'java').
 */
function rejudgeForm($table, $id)
{
    $ret = '<div id="rejudge" class="framed">' .
         addForm('rejudge.php') .
         addHidden('table', $table) .
         addHidden('id', $id);

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
function starts_with($haystack, $needle)
{
    return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
}
/**
 * Returns TRUE iff string $haystack ends with string $needle
 */
function ends_with($haystack, $needle)
{
    return mb_substr($haystack, mb_strlen($haystack)-mb_strlen($needle)) === $needle;
}

/**
 * tries to open corresponding zip archive
 */
function openZipFile($filename)
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
    $thumbsize = array(round($info[0]*$rescale),
                       round($info[1]*$rescale));

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

/**
 * Read problem description file and testdata from zip archive
 * and update problem with it, or insert new problem when probid=NULL.
 * Returns probid on success, or generates error on failure.
 */
function importZippedProblem($zip, $filename, $probid = null, $cid = -1)
{
    global $DB, $teamid, $cdatas;
    $prop_file = 'domjudge-problem.ini';
    $yaml_file = 'problem.yaml';
    $tle_file = '.timelimit';

    $ini_keys_problem = array('name', 'timelimit', 'special_run', 'special_compare');
    $ini_keys_contest_problem = array('probid', 'allow_submit', 'allow_judge', 'points', 'color');

    $def_timelimit = 10;

    // Read problem properties
    $ini_array = parse_ini_string($zip->getFromName($prop_file));

    // Only preserve valid keys:
    $ini_array_problem = array_intersect_key($ini_array, array_flip($ini_keys_problem));
    $ini_array_contest_problem = array_intersect_key($ini_array, array_flip($ini_keys_contest_problem));

    // Set timelimit from alternative source:
    if (!isset($ini_array_problem['timelimit']) &&
         ($str = $zip->getFromName($tle_file))!==false) {
        $ini_array_problem['timelimit'] = trim($str);
    }

    // Take problem:externalid from zip filename, and use as backup for
    // problem:name and contestproblem:shortname if these are not specified.
    $extid = preg_replace('[^a-zA-Z0-9-_]', '', basename($filename, '.zip'));
    if ((string)$extid==='') {
        error("Could not extract an identifier from '" . basename($filename) . "'.");
    }
    if (!array_key_exists('externalid', $ini_array_problem)) {
        $ini_array_problem['externalid'] = $extid;
    }

    // Rename old probid to contestproblem:shortname
    if (isset($ini_array_contest_problem['probid'])) {
        $shortname = $ini_array_contest_problem['probid'];
        unset($ini_array_contest_problem['probid']);
        $ini_array_contest_problem['shortname'] = $shortname;
    } else {
        $ini_array_contest_problem['shortname'] = $extid;
    }

    // Set default of 1 point for a problem if not specified
    if (!isset($ini_array_contest_problem['points'])) {
        $ini_array_contest_problem['points'] = 1;
    }

    if ($probid===null) {
        // Set sensible defaults for name and timelimit if not specified:
        if (!isset($ini_array_problem['name'])) {
            $ini_array_problem['name'] = $ini_array_contest_problem['shortname'];
        }
        if (!isset($ini_array_problem['timelimit'])) {
            $ini_array_problem['timelimit'] = $def_timelimit;
        }

        $probid = $DB->q('RETURNID INSERT INTO problem (' .
                         implode(', ', array_keys($ini_array_problem)) .
                         ') VALUES (%As)', $ini_array_problem);

        if ($cid != -1) {
            $ini_array_contest_problem['cid'] = $cid;
            $ini_array_contest_problem['probid'] = $probid;
            $DB->q('INSERT INTO contestproblem (' .
                   implode(', ', array_keys($ini_array_contest_problem)) .
                   ') VALUES (%As)', $ini_array_contest_problem);
        }
    } else {
        if (count($ini_array_problem)>0) {
            $DB->q('UPDATE problem SET %S WHERE probid = %i', $ini_array_problem, $probid);
        }

        if ($cid != -1) {
            if ($DB->q("MAYBEVALUE SELECT probid FROM contestproblem
                        WHERE probid = %i AND cid = %i", $probid, $cid)) {
                // Remove keys that cannot be modified:
                unset($ini_array_contest_problem['probid']);
                if (count($ini_array_contest_problem)!=0) {
                    $DB->q('UPDATE contestproblem SET %S WHERE probid = %i AND cid = %i',
                           $ini_array_contest_problem, $probid, $cid);
                }
            } else {
                $ini_array_contest_problem['cid'] = $cid;
                $ini_array_contest_problem['probid'] = $probid;
                $DB->q('INSERT INTO contestproblem (' .
                       implode(', ', array_keys($ini_array_contest_problem)) .
                       ') VALUES (%As)', $ini_array_contest_problem);
            }
        }
    }

    // parse problem.yaml
    $problem_yaml = $zip->getFromName($yaml_file);
    if ($problem_yaml !== false) {
        $problem_yaml_data = spyc_load($problem_yaml);

        if (!empty($problem_yaml_data)) {
            if (isset($problem_yaml_data['uuid']) && $cid != -1) {
                $DB->q('UPDATE contestproblem SET shortname=%s
                        WHERE cid=%i AND probid=%i',
                       $problem_yaml_data['uuid'], $cid, $probid);
            }
            $yaml_array_problem = array();
            if (isset($problem_yaml_data['name'])) {
                if (is_array($problem_yaml_data['name'])) {
                    foreach ($problem_yaml_data['name'] as $lang => $name) {
                        // TODO: select a specific instead of the first language
                        $yaml_array_problem['name'] = $name;
                        break;
                    }
                } else {
                    $yaml_array_problem['name'] = $problem_yaml_data['name'];
                }
            }
            if (isset($problem_yaml_data['validator_flags'])) {
                $yaml_array_problem['special_compare_args'] = $problem_yaml_data['validator_flags'];
            }
            if (isset($problem_yaml_data['validation']) && $problem_yaml_data['validation'] == 'custom') {
                // search for validator
                $validator_files = array();
                for ($j = 0; $j < $zip->numFiles; $j++) {
                    $filename = $zip->getNameIndex($j);
                    if (starts_with($filename, "output_validators/") && !ends_with($filename, "/")) {
                        $validator_files[] = $filename;
                    }
                }
                if (sizeof($validator_files) == 0) {
                    echo "<p>Custom validator specified but not found.</p>\n";
                } else {
                    // file(s) have to share common directory
                    $validator_dir = mb_substr($validator_files[0], 0, mb_strrpos($validator_files[0], "/")) . "/";
                    $same_dir = true;
                    foreach ($validator_files as $validator_file) {
                        if (!starts_with($validator_file, $validator_dir)) {
                            $same_dir = false;
                            echo "<p>$validator_file does not start with $validator_dir</p>\n";
                            break;
                        }
                    }
                    if (!$same_dir) {
                        echo "<p>Found multiple custom output validators.</p>\n";
                    } else {
                        $tmpzipfiledir = exec("mktemp -d --tmpdir=" . TMPDIR, $dontcare, $retval);
                        if ($retval!=0) {
                            error("failed to create temporary directory");
                        }
                        chmod($tmpzipfiledir, 0700);
                        foreach ($validator_files as $validator_file) {
                            $content = $zip->getFromName($validator_file);
                            $filebase = basename($validator_file);
                            $newfilename = $tmpzipfiledir . "/" . $filebase;
                            file_put_contents($newfilename, $content);
                            if ($filebase === 'build' || $filebase === 'run') {
                                // mark special files as executable
                                chmod($newfilename, 0755);
                            }
                        }

                        exec("zip -r -j '$tmpzipfiledir/outputvalidator.zip' '$tmpzipfiledir'", $dontcare, $retval);
                        if ($retval!=0) {
                            error("failed to create zip file for output validator.");
                        }

                        $ovzip = dj_file_get_contents("$tmpzipfiledir/outputvalidator.zip");
                        $probname = $DB->q("VALUE SELECT name FROM problem
                                            WHERE probid=%i", $probid);
                        $ovname = $extid . "_cmp";
                        if ($DB->q("MAYBEVALUE SELECT execid FROM executable
                                    WHERE execid=%s", $ovname)) {
                            // avoid name clash
                            $clashcnt = 2;
                            while ($DB->q("MAYBEVALUE SELECT execid FROM executable
                                           WHERE execid=%s", $ovname . "_" . $clashcnt)) {
                                $clashcnt++;
                            }
                            $ovname = $ovname . "_" . $clashcnt;
                        }
                        $DB->q("INSERT INTO executable (execid, md5sum, zipfile,
                                description, type) VALUES (%s, %s, %s, %s, %s)",
                               $ovname, md5($ovzip), $ovzip,
                               'output validator for ' . $probname, 'compare');

                        $DB->q("UPDATE problem SET special_compare=%s
                                WHERE probid=%i", $ovname, $probid);

                        echo "<p>Added output validator '$ovname'.</p>\n";
                    }
                }
            }
            if (isset($problem_yaml_data['limits'])) {
                if (isset($problem_yaml_data['limits']['memory'])) {
                    $yaml_array_problem['memlimit'] = 1024 * $problem_yaml_data['limits']['memory'];
                }
                if (isset($problem_yaml_data['limits']['output'])) {
                    $yaml_array_problem['outputlimit'] = 1024 * $problem_yaml_data['limits']['output'];
                }
            }

            if (sizeof($yaml_array_problem) > 0) {
                $DB->q('UPDATE problem SET %S WHERE probid = %i', $yaml_array_problem, $probid);
            }
        }
    }

    // Add problem statement
    foreach (array('pdf', 'html', 'txt') as $type) {
        $text = $zip->getFromName('problem.' . $type);
        if ($text!==false) {
            $DB->q('UPDATE problem SET problemtext = %s, problemtext_type = %s
                    WHERE probid = %i', $text, $type, $probid);
            echo "<p>Added problem statement from: <tt>problem.$type</tt></p>\n";
            break;
        }
    }

    // Insert/update testcases
    $maxrank = 1 + $DB->q('VALUE SELECT max(rank) FROM testcase
                           WHERE probid = %i', $probid);

    // first insert sample, then secret data in alphabetical order
    foreach (array('sample', 'secret') as $type) {
        $ncases = 0;
        $datafiles = array();
        for ($j = 0; $j < $zip->numFiles; $j++) {
            $filename = $zip->getNameIndex($j);
            if (starts_with($filename, "data/$type/") && ends_with($filename, ".in")) {
                $basename = basename($filename, ".in");
                $fileout = "data/$type/" . $basename . ".ans";
                if ($zip->locateName($fileout) !== false) {
                    $datafiles[] = $basename;
                }
            }
        }
        asort($datafiles);

        echo "<ul>\n";
        foreach ($datafiles as $datafile) {
            $testin  = $zip->getFromName("data/$type/$datafile.in");
            $testout = $zip->getFromName("data/$type/$datafile.ans");
            $description = $datafile;
            if (($descfile = $zip->getFromName("data/$type/$datafile.desc")) !== false) {
                $description .= ": \n" . $descfile;
            }
            $image_file = $image_type = $image_thumb = false;
            foreach (array('png', 'jpg', 'jpeg', 'gif') as $img_ext) {
                $image_fname = "data/$type/$datafile" . '.' . $img_ext;
                if (($image_file = $zip->getFromName($image_fname)) !== false) {
                    $image_type = get_image_type($image_file, $errormsg);
                    if ($image_type === false) {
                        warning("reading '$image_fname': " . $errormsg);
                        $image_file = false;
                    } elseif ($image_type !== ($img_ext=='jpg' ? 'jpeg' : $img_ext)) {
                        warning("extension of '$image_fname' does not match type '$image_type'");
                        $image_file = false;
                    } else {
                        $image_thumb = get_image_thumb($image_file, $errormsg);
                        if ($image_thumb === false) {
                            $image_thumb = null;
                            warning("reading '$image_fname': " . $errormsg);
                        }
                    }
                    break;
                }
            }

            $md5in  = md5($testin);
            $md5out = md5($testout);

            // Skip testcases that already exist identically
            $id = $DB->q('MAYBEVALUE SELECT testcaseid FROM testcase
                          WHERE md5sum_input = %s AND md5sum_output = %s AND
                          description = %s AND sample = %i AND probid = %i',
                         $md5in, $md5out, $description, ($type == 'sample' ? 1 : 0), $probid);
            if (isset($id)) {
                echo "<li>Skipped $type testcase <tt>$datafile</tt>: already exists</li>\n";
                continue;
            }

            $tc = $DB->q('RETURNID INSERT INTO testcase (probid, rank, sample,
                          md5sum_input, md5sum_output, input, output, description' .
                         ($image_file !== false ? ', image, image_thumb, image_type' : '') .
                         ') VALUES (%i, %i, %i, %s, %s, %s, %s, %s' .
                         ($image_file !== false ? ', %s, %s, %s' : '%_ %_ %_') .
                         ')',
                         $probid, $maxrank, $type == 'sample' ? 1 : 0,
                         $md5in, $md5out, $testin, $testout, $description,
                         $image_file, $image_thumb, $image_type);
            $maxrank++;
            $ncases++;

            // FIXME: this should be done after logging a problem create event.
            eventlog('testcase', $tc, 'create');

            echo "<li>Added $type testcase from: <tt>$datafile.{in,ans}</tt></li>\n";
        }
        echo "</ul>\n<p>Added $ncases $type testcase(s).</p>\n";
    }

    // submit reference solutions
    if ($cid == -1) {
        echo "<p>No jury solutions added: problem is not linked to a contest (yet).</p>\n";
    } elseif (empty($teamid)) {
        echo "<p>No jury solutions added: must associate team with your user first.</p>\n";
    } elseif ($DB->q('MAYBEVALUE SELECT allow_submit FROM problem
                      INNER JOIN contestproblem using (probid)
                      WHERE probid = %i AND cid = %i', $probid, $cid)) {
        // First find all submittable languages:
        $langs = $DB->q('KEYVALUETABLE SELECT langid, extensions
                         FROM language WHERE allow_submit = 1');

        $njurysols = 0;
        echo "<ul>\n";
        for ($j = 0; $j < $zip->numFiles; $j++) {
            $path = $zip->getNameIndex($j);
            if (!starts_with($path, 'submissions/')) {
                // Skipping non-submission files silently.
                continue;
            }
            $pathcomp = explode('/', $path);
            if (!((count($pathcomp)==3 && !empty($pathcomp[2])) ||
                  (count($pathcomp)==4 &&  empty($pathcomp[3])))) {
                // Skipping files and directories at the wrong level.
                // Note that multi-file submissions sit in a subdirectory.
                continue;
            }

            if (count($pathcomp)==3) {
                // Single file submission
                $files = array($pathcomp[2]);
                $indices = array($j);
            } else {
                // Multi file submission
                $files = array();
                $indices = array();
                $len = mb_strrpos($path, '/') + 1;
                $prefix = mb_substr($path, 0, $len);
                for ($k = 0; $k < $zip->numFiles; $k++) {
                    $file = $zip->getNameIndex($k);
                    // Only allow multi-file submission with all files
                    // directly under the directory.
                    if (strncmp($prefix, $file, $len)==0 && mb_strlen($file)>$len &&
                         mb_strrpos($file, '/')+1==$len) {
                        $files[] = mb_substr($file, $len);
                        $indices[] = $k;
                    }
                }
            }

            unset($langid);
            foreach ($files as $file) {
                $parts = explode(".", $file);
                if (count($parts)==1) {
                    continue;
                }
                $extension = end($parts);
                foreach ($langs as $key => $exts) {
                    if (in_array($extension, dj_json_decode($exts))) {
                        $langid = $key;
                        break 2;
                    }
                }
            }
            if (empty($langid)) {
                echo "<li>Could not add jury solution <tt>$path</tt>: unknown language.</li>\n";
            } else {
                $expectedResult = normalizeExpectedResult($pathcomp[1]);
                $results = null;
                $tmpfiles = array();
                $totalsize = 0;
                for ($k=0; $k<count($files); $k++) {
                    $source = $zip->getFromIndex($indices[$k]);
                    if ($results===null) {
                        $results = getExpectedResults($source);
                    }
                    if (!($tmpfname = tempnam(TMPDIR, "ref_solution-"))) {
                        error("Could not create temporary file in directory " . TMPDIR);
                    }
                    if (file_put_contents($tmpfname, $source)===false) {
                        error("Could not write to temporary file '$tmpfname'.");
                    }
                    $tmpfiles[] = $tmpfname;
                    $totalsize += filesize($tmpfname);
                }
                if ($results === null) {
                    $results[] = $expectedResult;
                } elseif (!in_array($expectedResult, $results)) {
                    warning("annotated result '" . implode(', ', $results) . "' does not match directory for $filename");
                }
                if ($totalsize <= dbconfig_get('sourcesize_limit')*1024) {
                    $sid = submit_solution(
                        $teamid,
                        $probid,
                        $cid,
                        $langid,
                        $tmpfiles,
                        $files,
                        /* origsubmitid= */ null,
                        /* entry_point= */ '__auto__'
                    );
                    $DB->q('UPDATE submission SET expected_results=%s WHERE submitid=%i',
                           dj_json_encode($results), $sid);

                    echo "<li>Added jury solution from: <tt>$path</tt></li>\n";
                    $njurysols++;
                } else {
                    echo "<li>Could not add jury solution <tt>$path</tt>: too large.</li>\n";
                }

                foreach ($tmpfiles as $f) {
                    unlink($f);
                }
            }
        }
        echo "</ul>\n<p>Added $njurysols jury solution(s).</p>\n";
    } else {
        echo "<p>No jury solutions added: problem not submittable</p>\n";
    }
    if (!in_array($cid, array_keys($cdatas))) {
        echo "<p>The corresponding contest is not activated yet." .
            "To view the submissions in the submissions list, you have to activate the contest first.</p>\n";
    }

    return $probid;
}

// dis- or re-enable what caused an internal error
function set_internal_error($disabled, $cid, $value)
{
    global $DB, $api;
    switch ($disabled['kind']) {
        case 'problem':
            $DB->q('RETURNAFFECTED UPDATE contestproblem
                    SET allow_judge=%i
                    WHERE cid=%i AND probid=%i',
                   $value, $cid, $disabled['probid']);
            break;
        case 'judgehost':
            $DB->q('RETURNAFFECTED UPDATE judgehost
                    SET active=%i
                    WHERE hostname=%s',
                   $value, $disabled['hostname']
            );
            break;
        case 'language':
            $DB->q('RETURNAFFECTED UPDATE language
                    SET allow_judge=%i
                    WHERE langid=%s',
                   $value, $disabled['langid']);
            break;
        default:
            $api->createError("unknown internal error kind '" . $disabled['kind'] . "'");
    }
}
