<?php

/**
 * Common functions in jury interface
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * Return a link to add a new row to a specific table.
 */
function addLink($table, $multi = false)
{
	return "<a href=\"" . htmlspecialchars($table) . ".php?cmd=add\">" .
		"<img src=\"../images/add" . ($multi?"-multi":"") .
		".png\" alt=\"add" . ($multi?" multiple":"") .
		"\" title=\"add" .   ($multi?" multiple":"") .
		" new " . htmlspecialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to edit a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 * Includes a referrer field, which notes the page on which this function
 * was called, so edit.php can return us back here.
 */
function editLink($table, $value, $multi = false)
{
	return "<a href=\"" . htmlspecialchars($table) . ".php?cmd=edit" .
		($multi ? "" : "&amp;id=" . urlencode($value) ) .
		"&amp;referrer=" . urlencode(basename($_SERVER['SCRIPT_NAME']) .
		(empty($_REQUEST['id']) ? '' : '?id=' . urlencode($_REQUEST['id']))) .
		"\">" .
		"<img src=\"../images/edit" . ($multi?"-multi":"") .
		".png\" alt=\"edit" . ($multi?" multiple":"") .
		"\" title=\"edit " .   ($multi?"multiple ":"this ") .
		htmlspecialchars($table) . "\" class=\"picto\" /></a>";
}

/**
 * Return a link to delete a specific data element from a given table.
 * Takes the table, the key field to match on and the value.
 */
function delLink($table, $field, $value)
{
	return delLinkMultiple($table, array($field), array($value));
}

/**
 * Return a link to delete a specific data element from a given table.
 * Takes the table, the key fields to match on and the values.
 */
function delLinkMultiple($table, $fields, $values, $referrer = '')
{
	$arguments = '';
	foreach ($fields as $i => $field) {
		$arguments .= '&amp;' . $field . '=' . urlencode($values[$i]);
	}
	return "<a href=\"delete.php?table=" . urlencode($table) . $arguments .
	       "&amp;referrer=" . urlencode($referrer)
	       ."\"><img src=\"../images/delete.png\" " .
	       "alt=\"delete\" title=\"delete this " . htmlspecialchars($table) .
	       "\" class=\"picto\" /></a>";
}

/**
 * Returns a link to export a problem as zip-file.
 *
 */
function exportLink($probid)
{
	return '<a href="export.php?id=' . urlencode($probid) .
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
	$ret = addForm('rejudge.php') .
		addHidden('table', $table) .
		addHidden('id', $id);

	$button = 'REJUDGE this submission';
	$question = "Rejudge submission s$id?";
	$disabled = false;

	// special case submission
	if ( $table == 'submission' ) {

		// disable the form button if there are no valid judgings anyway
		// (nothing to rejudge) or if the result is already correct
		global $DB;
		$validresult = $DB->q('MAYBEVALUE SELECT result FROM judging WHERE
		                       submitid = %i AND valid = 1', $id);

		if ( IS_ADMIN ) {
			if ( ! $validresult ) {
				$question = "Restart judging of PENDING submission s$id, " .
					'are you sure?';
				$button = 'RESTART judging';
			} elseif ( $validresult == 'correct' ) {
				$question = "Rejudge CORRECT submission s$id, " .
					'are you sure?';
			}
		} else {
			if ( ! $validresult || $validresult == 'correct' ) {
				$disabled = true;
			}
		}
	} else if ( $table == 'contest' ) {
		$button = "REJUDGE ALL for $table $id";
		$question = "Rejudge all submissions for this $table?";
	} else {
		$button = "REJUDGE ALL for $table $id";
		$question = "Rejudge all non-CORRECT submissions for this $table?";
	}

	$ret .= '<input type="submit" value="' . htmlspecialchars($button) . '" ' .
		($disabled ? 'disabled="disabled"' : 'onclick="return confirm(\'' .
		htmlspecialchars($question) . '\');"') . " />\n" . addEndForm();

	return $ret;
}


/**
 * Returns TRUE iff string $haystack starts with string $needle
 */
function starts_with($haystack, $needle) {
	return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
}
/**
 * Returns TRUE iff string $haystack ends with string $needle
 */
function ends_with($haystack, $needle) {
	return mb_substr($haystack, mb_strlen($haystack)-mb_strlen($needle)) === $needle;
}

/**
 * tries to open corresponding zip archive
 */
function openZipFile($filename) {
	$zip = new ZipArchive;
	$res = $zip->open($filename, ZIPARCHIVE::CHECKCONS);
	if ($res === ZIPARCHIVE::ER_NOZIP || $res === ZIPARCHIVE::ER_INCONS) {
		error("no valid zip archive given");
	} else if ($res === ZIPARCHIVE::ER_MEMORY) {
		error("not enough memory to extract zip archive");
	} else if ($res !== TRUE) {
		error("unknown error while extracting zip archive");
	}

	return $zip;
}

/**
 * Parse a configuration string
 * (needed if PHP version < 5.3)
 */
if (!function_exists('parse_ini_string')) {
	function parse_ini_string($ini, $process_sections = false, $scanner_mode = null) {
		# Generate a temporary file.
		$tempname = tempnam('/tmp', 'ini');
		$fp = fopen($tempname, 'w');
		fwrite($fp, $ini);
		$ini = parse_ini_file($tempname, !empty($process_sections));
		fclose($fp);
		@unlink($tempname);
		return $ini;
	}
}

$matchstrings = array('@EXPECTED_RESULTS@: ',
		      '@EXPECTED_SCORE@: ');


function normalizeExpectedResult($result) {
	// Remap results as specified by the Kattis problem package format,
	// see: http://www.problemarchive.org/wiki/index.php/Problem_Format
	$resultremap = array('ACCEPTED' => 'CORRECT',
			     'WRONG_ANSWER' => 'WRONG-ANSWER',
			     'TIME_LIMIT_EXCEEDED' => 'TIMELIMIT',
			     'RUN_TIME_ERROR' => 'RUN-ERROR');

	$result = trim(mb_strtoupper($result));
	if ( in_array($result,array_keys($resultremap)) ) {
		return $resultremap[$result];
	}
	return $result;
}

/**
 * checks given source file for expected results string
 * returns NULL if no such string exists
 * returns array of expected results otherwise
 */
function getExpectedResults($source) {
	global $matchstrings;
	$pos = FALSE;
	foreach ( $matchstrings as $matchstring ) {
		if ( ($pos = mb_stripos($source,$matchstring)) !== FALSE ) break;
	}

	if ( $pos === FALSE) {
		return NULL;
	}

	$beginpos = $pos + mb_strlen($matchstring);
	$endpos = mb_strpos($source,"\n",$beginpos);
	$str = mb_substr($source,$beginpos,$endpos-$beginpos);
	$results = explode(',',trim(mb_strtoupper($str)));

	foreach ( $results as $key => $val ) {
		$results[$key] = normalizeExpectedResult($val);
	}

	return $results;
}

/**
 * Read problem description file and testdata from zip archive
 * and update problem with it, or insert new problem when probid=NULL.
 * Returns probid on success, or generates error on failure.
 */
function importZippedProblem($zip, $probid = NULL, $cid = -1)
{
	global $DB, $teamid, $cdatas, $matchstrings;
	$prop_file = 'domjudge-problem.ini';

	$ini_keys_problem = array('name', 'timelimit', 'special_run', 'special_compare');
	$ini_keys_contest_problem = array('probid', 'allow_submit', 'allow_judge', 'color');

	$def_timelimit = 10;

	// Read problem properties
	$ini_array = parse_ini_string($zip->getFromName($prop_file));

	if ( empty($ini_array) ) {
		if ( $probid===NULL ) {
			error("Need '" . $prop_file . "' file when adding a new problem.");
		}
	} else {
		// Only preserve valid keys:
		$ini_array_problem = array_intersect_key($ini_array,array_flip($ini_keys_problem));
		$ini_array_contest_problem = array_intersect_key($ini_array,array_flip($ini_keys_contest_problem));

		if ( $probid===NULL ) {
			if ( !isset($ini_array_contest_problem['probid']) ) {
				error("Need 'probid' in '" . $prop_file . "' when adding a new problem.");
			}
			// Set sensible defaults for name and timelimit if not specified:
			if ( !isset($ini_array_problem['name'])      ) $ini_array_problem['name'] = $ini_array_problem['probid'];
			if ( !isset($ini_array_problem['timelimit']) ) $ini_array_problem['timelimit'] = $def_timelimit;

			// rename probid to shortname
			$shortname = $ini_array_contest_problem['probid'];
			unset($ini_array_contest_problem['probid']);
			$ini_array_contest_problem['shortname'] = $shortname;

			$probid = $DB->q('RETURNID INSERT INTO problem (' .
			                 implode(', ',array_keys($ini_array_problem)) .
			                 ') VALUES %As', $ini_array_problem);

			if ($cid != -1) {
				$ini_array_contest_problem['cid'] = $cid;
				$ini_array_contest_problem['probid'] = $probid;
				$DB->q('INSERT INTO contestproblem (' .
				       implode(', ',array_keys($ini_array_contest_problem)) .
				       ') VALUES %As', $ini_array_contest_problem);
			}
		} else {

			$DB->q('UPDATE problem SET %S WHERE probid = %i', $ini_array_problem, $probid);

			if ( $cid != -1 ) {
				if ( $DB->q("MAYBEVALUE SELECT probid FROM contestproblem
				             WHERE probid = %i AND cid = %i", $probid, $cid) ) {
					// Remove keys that cannot be modified:
					unset($ini_array_contest_problem['probid']);
					$DB->q('UPDATE contestproblem SET %S WHERE probid = %i AND cid = %i',
					       $ini_array_contest_problem, $probid, $cid);
				} else {
					$shortname = $ini_array_contest_problem['probid'];
					unset($ini_array_contest_problem['probid']);
					$ini_array_contest_problem['shortname'] = $shortname;
					$ini_array_contest_problem['cid'] = $cid;
					$ini_array_contest_problem['probid'] = $probid;
					$DB->q('INSERT INTO contestproblem (' .
					       implode(', ',array_keys($ini_array_contest_problem)) .
					       ') VALUES %As', $ini_array_contest_problem);
				}
			}
		}
	}

	// Add problem statement
	foreach (array('pdf', 'html', 'txt') as $type) {
		$text = $zip->getFromName('problem.' . $type);
		if ( $text!==FALSE ) {
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
			if ( starts_with($filename, "data/$type/") && ends_with($filename, ".in") ) {
				$basename = basename($filename, ".in");
				$fileout = "data/$type/" . $basename . ".ans";
				if ( $zip->locateName($fileout) !== FALSE ) {
					$datafiles[] = $basename;
				}
			}
		}
		asort($datafiles);

		echo "<ul>\n";
		foreach ($datafiles as $datafile) {
			$testin  = $zip->getFromName("data/$type/$datafile.in");
			$testout = $zip->getFromName("data/$type/$datafile.ans");

			$DB->q('INSERT INTO testcase (probid, rank, sample,
				md5sum_input, md5sum_output, input, output, description)
				VALUES (%i, %i, %i, %s, %s, %s, %s, %s)',
				$probid, $maxrank, $type == 'sample' ? 1 : 0,
				md5($testin), md5($testout),
				$testin, $testout, $datafile);
			$maxrank++;
			$ncases++;
			echo "<li>Added $type testcase from: <tt>$datafile.{in,out}</tt></li>\n";
		}
		echo "</ul>\n<p>Added $ncases $type testcase(s).</p>\n";
	}

	// submit reference solutions
	if ( $cid == -1 ) {
		echo "<p>No jury solutions added: problem is not linked to a contest (yet).</p>\n";
	} else if ( empty($teamid) ) {
		echo "<p>No jury solutions added: must associate team with your user first.</p>\n";
	} else if ( $DB->q('VALUE SELECT allow_submit FROM problem INNER JOIN contestproblem using (probid) WHERE probid = %i AND cid = %i', $probid, $cid) ) {
		// First find all submittable languages:
		$langs = $DB->q('KEYVALUETABLE SELECT langid, extensions
 		                 FROM language WHERE allow_submit = 1');

		$njurysols = 0;
		echo "<ul>\n";
		for ($j = 0; $j < $zip->numFiles; $j++) {
			$filename = $zip->getNameIndex($j);
			$filename_parts = explode(".", $filename);
			$extension = end($filename_parts);
			if ( !starts_with($filename, 'submissions/') || ends_with($filename, '/') ) {
				// skipping non-submission files and directories silently
				continue;
			}
			unset($langid);
			foreach ( $langs as $key => $exts ) {
				if ( in_array($extension,json_decode($exts)) ) {
					$langid = $key;
					break;
				}
			}
			if ( empty($langid) ) {
				echo "<li>Could not add jury solution <tt>$filename</tt>: unknown language.</li>\n";
			} else {
				if ( !($tmpfname = tempnam(TMPDIR, "ref_solution-")) ) {
					error("Could not create temporary file in directory " . TMPDIR);
				}
				$offset = mb_strlen('submissions/');
				$expectedResult = normalizeExpectedResult(mb_substr($filename, $offset, mb_strpos($filename, '/', $offset) - $offset));
				$source = $zip->getFromIndex($j);
				$results = getExpectedResults($source);
				if ( $results === NULL ) {
					// annotate source code with expected result
					$source = "// added by import: " . $matchstrings[0] . $expectedResult . "\n" . $source;
				} else if ( !in_array($expectedResult, $results) ) {
					warning("annotated result '" . implode(', ', $results) . "' does not match directory for $filename");
				}
				file_put_contents($tmpfname, $source);
				if( filesize($tmpfname) <= dbconfig_get('sourcesize_limit')*1024 ) {
					submit_solution($teamid, $probid, $cid, $langid,
							array($tmpfname), array(basename($filename)));
					echo "<li>Added jury solution from: <tt>$filename</tt></li>\n";
					$njurysols++;
				} else {
					echo "<li>Could not add jury solution <tt>$filename</tt>: too large.</li>\n";
				}

				unlink($tmpfname);
			}
		}
		echo "</ul>\n<p>Added $njurysols jury solution(s).</p>\n";
	} else {
		echo "<p>No jury solutions added: problem not submittable</p>\n";
	}
	if ( !in_array($cid, array_keys($cdatas)) ) {
		echo "<p>The corresponding contest is not activated yet." .
			"To view the submissions in the submissions list, you have to activate the contest first.</p>\n";
	}

	return $probid;
}
