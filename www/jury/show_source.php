<?php
/**
 * Show source code from the database.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

function parseSourceDiff($difftext){
	$line = strtok($difftext,"\n"); //first line
	$return = '';
	while ( strlen($line) != 0 ) {
		// Strip any additional DOS/MAC newline characters:
		$line = trim($line, "\r\n");
		switch ( substr($line,0,1) ) {
		case '-':
			$formdiffline = "<span class='diff-old'>".htmlspecialchars($line)."</span>";
			break;
		case '+':
			$formdiffline = "<span class='diff-new'>".htmlspecialchars($line)."</span>";
			break;
		default:
			$formdiffline = htmlspecialchars($line);
		}
		$return .= $formdiffline . "\n";
		$line = strtok("\n");
	}
	return $return;
}

function createDiff($source, $newfile, $id, $oldsource, $oldfile, $oldid) {
	// Try different ways of diffing, in order of preference.
	if ( function_exists('xdiff_string_diff') ) {
		// The PECL xdiff PHP-extension.

		$difftext = xdiff_string_diff($oldsource['sourcecode'],
		                              $source['sourcecode'],2);

	} elseif ( !(bool) ini_get('safe_mode') ||
		       strtolower(ini_get('safe_mode'))=='off' ) {
		// Only try executing diff when safe_mode is off, otherwise
		// the shell_exec will fail.

		if ( is_readable($oldfile) && is_readable($newfile) ) {
			// A direct diff on the sources in the SUBMITDIR.

			$difftext = `diff -Bt -U 2 $oldfile $newfile 2>&1`;

		} else {
			// Try generating temporary files for executing diff.

			$oldfile = mkstemps(TMPDIR."/source-old-s$oldid-XXXXXX",0);
			$newfile = mkstemps(TMPDIR."/source-new-s$id-XXXXXX",0);

			if( ! $oldfile || ! $newfile ) {
				$difftext = "DOMjudge: error generating temporary files for diff.";
			} else {
				$oldhandle = fopen($oldfile,'w');
				$newhandle = fopen($newfile,'w');

				if( ! $oldhandle || ! $newhandle ) {
					$difftext = "DOMjudge: error opening temporary files for diff.";
				} else {
					if ( (fwrite($oldhandle,$oldsource['sourcecode'])===FALSE) ||
					     (fwrite($newhandle,   $source['sourcecode'])===FALSE) ) {
						$difftext = "DOMjudge: error writing temporary files for diff.";
					} else {
						$difftext = `diff -Bt -U 2 $oldfile $newfile 2>&1`;
					}
				}
				if ( $oldhandle ) fclose($oldhandle);
				if ( $newhandle ) fclose($newhandle);
			}

			if ( $oldfile ) unlink($oldfile);
			if ( $newfile ) unlink($newfile);
		}
	} else {
		$difftext = "DOMjudge: diff functionality not available in PHP or via shell_exec.";
	}

	return $difftext;
}

require('init.php');

$id = (int)$_GET['id'];

/* FIXME: this currently only shows the first source file of a
 * multiple file submission; need to think about how to show and diff
 * a multifile submission.
 */
$source = $DB->q('MAYBETUPLE SELECT s.*, f.*, COUNT(g.rank) AS nfiles
                  FROM submission s
                  LEFT JOIN submission_file f ON(s.submitid=f.submitid AND f.rank=0)
                  LEFT JOIN submission_file g ON(s.submitid=g.submitid)
                  WHERE s.submitid = %i GROUP BY g.submitid',$id);
if ( empty($source) ) error ("Submission $id not found");

$sourcefile = getSourceFilename($source);

// Download was requested
if ( isset($_GET['fetch']) ) {
	header("Content-Type: text/plain; name=\"$sourcefile\"; charset=" . DJ_CHARACTER_SET);
	header("Content-Disposition: inline; filename=\"$sourcefile\"");
	header("Content-Length: " . strlen($source['sourcecode']));

	echo $source['sourcecode'];
	exit;
}

$sub_resub = "submission";
if ( $source['origsubmitid'] !== NULL ) {
	$origsource = $DB->q('MAYBETUPLE SELECT s.*, f.*, COUNT(g.rank) AS nfiles
			     FROM submission s
			     LEFT JOIN submission_file f ON(s.submitid=f.submitid AND f.rank=0)
			     LEFT JOIN submission_file g ON(s.submitid=g.submitid)
			     WHERE s.submitid = %i',
			    $source['origsubmitid']);
	$oldsource = $DB->q('MAYBETUPLE SELECT s.*, f.*, COUNT(g.rank) AS nfiles
			     FROM submission s
			     LEFT JOIN submission_file f ON(s.submitid=f.submitid AND f.rank=0)
			     LEFT JOIN submission_file g ON(s.submitid=g.submitid)
			     WHERE teamid = %s AND probid = %s AND langid = %s AND submittime < %s
			     AND origsubmitid = %i
			     GROUP BY g.submitid ORDER BY submittime DESC LIMIT 1',
			    'domjudge',$source['probid'],$source['langid'],
			    $source['submittime'], $source['origsubmitid']);
	$sub_resub = "resubmit";
} else {
	$oldsource = $DB->q('MAYBETUPLE SELECT s.*, f.*, COUNT(g.rank) AS nfiles
			     FROM submission s
			     LEFT JOIN submission_file f ON(s.submitid=f.submitid AND f.rank=0)
			     LEFT JOIN submission_file g ON(s.submitid=g.submitid)
			     WHERE teamid = %s AND probid = %s AND langid = %s AND submittime < %s
			     GROUP BY g.submitid ORDER BY submittime DESC LIMIT 1',
			    $source['teamid'],$source['probid'],$source['langid'],
			    $source['submittime']);
}

$title = 'Source: ' . htmlspecialchars($sourcefile);
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/highlight.php');

if ( $source['nfiles']>1 ) warning("Submission $id has multiple source files");

if ( $origsource ) {
	$origid = $source['origsubmitid'];
	$origtid = $origsource['teamid'];
	echo "<p>(This is a resubmit; original submission was " .
		"<a href=\"submission.php?id=$origid\">s$origid</a> of " .
		"team <a href=\"team.php?id=$origtid\">$origtid</a>.)</p>";
}
if ( $oldsource ) {
	echo "<p><a href=\"#diff\">Go to diff to previous $sub_resub</a></p>\n\n";
}
if ( $origsource ) {
	echo "<p><a href=\"#origdiff\">Go to diff to original submission</a></p>\n\n";
}

echo '<h2 class="filename"><a name="source"></a>' . $sub_resub . ' ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source: " .
	htmlspecialchars($sourcefile) . " (<a " .
	"href=\"show_source.php?id=$id&amp;fetch=1\">download</a>, <a " .
	"href=\"edit_source.php?id=$id\">edit</a>)</h2>\n\n";

if ( strlen($source['sourcecode'])==0 ) {
	// Someone submitted an empty file. Cope gracefully.
	echo "<p class=\"nodata\">empty file</p>\n\n";
} elseif ( strlen($source['sourcecode']) < 10 * 1024 ) {
	// Source < 10kB (for longer source code,
	// highlighter tends to take very long time or timeout)
	highlight($source['sourcecode'], $source['langid']);
} else {
	// Fall back to built-in simple formatter
	highlight_native($source['sourcecode'], $source['langid']);
}


// show diff to old source
if ( $oldsource ) {
	if ( $oldsource['nfiles']>1 ) {
		warning("Submission $oldsource[submitid] has multiple source files");
	}

	$oldsourcefile = getSourceFilename($oldsource);

	$oldfile = SUBMITDIR.'/'.$oldsourcefile;
	$newfile = SUBMITDIR.'/'.$sourcefile;
	$oldid = (int)$oldsource['submitid'];

	$difftext = createDiff($source, $newfile, $id, $oldsource, $oldfile, $oldid);

	echo '<h2 class="filename"><a name="diff"></a>Diff to ' . $sub_resub . ' ' .
		"<a href=\"submission.php?id=$oldid\">s$oldid</a> source: " .
		"<a href=\"show_source.php?id=$oldid\">" .
		htmlspecialchars($oldsourcefile) . "</a></h2>\n\n";

	echo '<pre class="output_text">' . parseDiff($difftext) . "</pre>\n\n";
}

// show diff to original source
if ( $origsource ) {
	if ( $origsource['nfiles']>1 ) {
		warning("Submission $origsource[submitid] has multiple source files");
	}

	$oldsourcefile = getSourceFilename($origsource);

	$oldfile = SUBMITDIR.'/'.$oldsourcefile;
	$newfile = SUBMITDIR.'/'.$sourcefile;
	$oldid = (int)$origsource['submitid'];

	$difftext = createDiff($source, $newfile, $id, $origsource, $oldfile, $oldid);

	echo '<h2 class="filename"><a name="origdiff"></a>Diff to original source ' .
		"<a href=\"submission.php?id=$oldid\">s$oldid</a> source: " .
		"<a href=\"show_source.php?id=$oldid\">" .
		htmlspecialchars($oldsourcefile) . "</a></h2>\n\n";

	echo '<pre class="output_text">' . parseSourceDiff($difftext) . "</pre>\n\n";
}

require(LIBWWWDIR . '/footer.php');
