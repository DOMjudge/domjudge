<?php

/**
 * automatically verifies judgings that have a unique result
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = 'Judging Verifier';
require(LIBWWWDIR . '/header.php');

requireAdmin();

echo "<h1>Judging Verifier</h1>";

$nchecked = 0;
$nunchecked = 0;
$nverified = 0;
$nmanual = 0;
$nerrors = 0;

$matchstring = '@EXPECTED_RESULTS@: ';

$res = $DB->q("SELECT s.*, j.judgingid, j.result, j.verified, j.jury_member
               FROM submission s
               LEFT JOIN judging j ON (s.submitid = j.submitid AND j.valid=1)
	       WHERE j.verified = 0");

$unchecked = "";
$unexpected = "";
$multiple = "";
$verified = "";

while( $row = $res->next() ) {
	if ( $pos = strpos($row['sourcecode'],$matchstring) ) {
		$nchecked++;

		$beginpos = $pos + strlen($matchstring);
		$endpos = strpos($row['sourcecode'],"\n",$beginpos);
		$results = explode(',',trim(substr($row['sourcecode'],$beginpos,$endpos-$beginpos)));

		$sid = $row['submitid'];
		$result = strtoupper($row['result']);

		if ( !in_array($result,$results) ) {
			$unexpected .= "<li><a href=\"submission.php?id=" . $sid
				. "\">submission $sid</a> has unexpected result '$result', "
				. "should be one of: "
				. implode(', ', $results)
				. "</li>\n";
			$nerrors++;
		} else if ( count($results)>1 ) {
			$multiple .= "<li><a href=\"submission.php?id=" . $sid 
				. "\">submission $sid</a> is judged as $result, "
				. "but has multiple possible outcomes ("
				. implode(', ', $results)
				. ")</li>\n";
			$nmanual++;
		} else {
			// Judging result is as expected, set judging to verified:
			if ( $row['verified']!=1 ) {
				$DB->q('UPDATE judging SET verified = 1, jury_member = \'auto-verifier\'
				        WHERE judgingid = %i', $row['judgingid']);
			}
			$verified .= "<li>verified <a href=\"submission.php?id=" . $sid . "\">submission $sid</a> as '$result'</li>\n";
			$nverified++;
		}
	} else {
		$sid = $row['submitid'];
		$nunchecked++;
		$unchecked .= "<li>'$matchstring' not found in <a href=\"submission.php?id=" . $sid 
			. "\">submission $sid</a>, leaving submission unchecked</li>\n";
	}
}

echo "checked $nchecked submissions: " .
	   "$nverified automatically verified, $nmanual to check manually, " .
	   "$nerrors unexpected results<br/>\n";
if ( $nunchecked > 0 ) {
	echo "not checked $nunchecked submissions<br/>\n";
}
	

if ( $nerrors > 0 ) {
	echo "<h2>Unexpected results</h2>";
	echo "<ul>\n";
	echo $unexpected;
	echo "</ul>\n";
}
if ( $nmanual > 0 ) {
	echo "<h2>Check manually</h2>";
	echo "<ul>\n";
	echo $multiple;
	echo "</ul>\n";
}
if ( $nverified > 0 ) {
	echo "<h2>Automatically verified</h2>";
	echo "<ul>\n";
	echo $verified;
	echo "</ul>\n";
}
if ( $nunchecked > 0 ) {
	echo "<h2>Unchecked Submissions</h2>";
	echo "<ul>\n";
	echo $unchecked;
	echo "</ul>\n";
}

require(LIBWWWDIR . '/footer.php');
