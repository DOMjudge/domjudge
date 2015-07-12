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

if ( ! $prob ) error("Missing or invalid problem id");

function filebase($probid, $rank)
{
	return 'p' . htmlspecialchars($probid) . '.t' . $rank . '.';
}

// Download testcase
if ( isset($_GET['fetch']) && in_array($_GET['fetch'], $FILES) ) {
	$rank  = $_GET['rank'];
	$fetch = $_GET['fetch'];

	if ( $fetch=='image' ) {
		$ext = $DB->q('MAYBEVALUE SELECT image_type
		               FROM testcase WHERE probid = %i AND rank = %i',
		              $probid, $rank);
		$type = 'image/' . $ext;
	} else {
		$ext = substr($fetch,0,-3);
		$type = 'text/plain';
	}
	$filename = filebase($prob['probid'],$rank) . $ext;

	$size = $DB->q("MAYBEVALUE SELECT OCTET_LENGTH($fetch)
	                FROM testcase WHERE probid = %i AND rank = %i",
	               $probid, $rank);

	// sanity check before we start to output headers
	if ( $size===NULL || !is_numeric($size)) error("Problem while fetching testcase");

	header("Content-Type: $type; name=\"$filename\"");
	header("Content-Disposition: attachment; filename=\"$filename\"");
	header("Content-Length: $size");

	// This may not be good enough for large testsets, but streaming them
	// directly from the database query result seems overkill to implement.
	echo $DB->q("VALUE SELECT SQL_NO_CACHE $fetch FROM testcase
	             WHERE probid = %i AND rank = %i", $probid, $rank);

	exit(0);
}

// We may need to re-update the testcase data, so make it a function.
function get_testcase_data()
{
	global $DB, $data, $probid;

	$data = $DB->q('KEYTABLE SELECT rank AS ARRAYKEY, testcaseid, rank,
	                description, sample, image_type,
	                OCTET_LENGTH(input)  AS size_input,  md5sum_input,
	                OCTET_LENGTH(output) AS size_output, md5sum_output,
	                OCTET_LENGTH(image)  AS size_image
	                FROM testcase WHERE probid = %i ORDER BY rank', $probid);
}
get_testcase_data();

// Reorder testcases
if ( isset ($_GET['move']) ) {
	$move = $_GET['move'];
	$rank = (int)$_GET['rank'];

	// First find testcase to switch with
	$last = NULL;
	$other = NULL;
	foreach( $data as $curr => $row ) {
		if ( $curr==$rank && $move=='up' ) {
			$other = $last;
			break;
		}
		if ( $rank==$last && $move=='down' && $last!==NULL ) {
			$other = $curr;
			break;
		}
		$last = $curr;
	}

	if ( $other!==NULL ) {
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

	// Redirect to the original page to prevent accidental redo's
	header('Location: testcase.php?probid=' . urlencode($probid));
	return;
}

$title = 'Testcases for problem p'.htmlspecialchars(@$probid).' - '.htmlspecialchars($prob['name']);

require(LIBWWWDIR . '/header.php');

echo "<h1>" . $title ."</h1>\n\n";

$result = '';
if ( isset($_POST['probid']) && IS_ADMIN ) {

	$maxrank = 0;
	foreach($data as $rank => $row) {
	foreach($FILES as $file) {

		if ( $rank>$maxrank ) $maxrank = $rank;

		$fileid = 'update_'.$file;
		if ( !empty($_FILES[$fileid]['name'][$rank]) ) {

			// Check for upload errors:
			checkFileUpload ( $_FILES[$fileid]['error'][$rank] );

			$content = file_get_contents($_FILES[$fileid]['tmp_name'][$rank]);
			if ( $DB->q("VALUE SELECT count(testcaseid)
			             FROM testcase WHERE probid = %i AND rank = %i",
			            $probid, $rank)==0 ) {
				error("cannot find testcase $rank for probid = $probid");
			}

			if ( $file=='image' ) {
				list($thumb, $type) = get_image_thumb_type($content);

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
			    htmlspecialchars($_FILES[$fileid]['name'][$rank]) .
			    " (" . printsize($_FILES[$fileid]['size'][$rank]) . ")";
			if ( $file=='output' &&
			     $_FILES[$fileid]['size'][$rank]>dbconfig_get('output_limit')*1024 ) {
				$result .= ".<br /><b>Warning: file size exceeds " .
				    "<code>output_limit</code> of " . dbconfig_get('output_limit') .
				    " kB. This will always result in wrong answers!</b>";
			}
			$result .= "</li>\n";
		}
	}

	if ( isset($_POST['sample'][$rank]) ) {
		$DB->q('UPDATE testcase SET sample = %i WHERE probid = %i
		        AND rank = %i', $_POST['sample'][$rank], $probid, $rank);
		$result .= "<li>Set testcase $rank to be " .
		           ($_POST['sample'][$rank] ? "" : "not ") .
		           "a sample testcase</li>\n";
	}

	if ( isset($_POST['description'][$rank]) ) {
		$DB->q('UPDATE testcase SET description = %s WHERE probid = %i
		        AND rank = %i', $_POST['description'][$rank], $probid, $rank);
		auditlog('testcase', $probid, 'updated description', "rank $rank");

		$result .= "<li>Updated description for testcase $rank</li>\n";
	}

	} // end: foreach $data

	if ( !empty($_FILES['add_input']['name']) ||
		 !empty($_FILES['add_output']['name']) ) {

		$content = array();
		$rank = $maxrank + 1;
		foreach($FILES as $file) {
			if ( empty($_FILES['add_'.$file]['name']) ) {
				warning("No $file file specified for new testcase, ignoring.");
			} else {
				checkFileUpload ( $_FILES['add_'.$file]['error'] );
				$content[$file] = file_get_contents($_FILES['add_'.$file]['tmp_name']);
			}
		}

		$DB->q("INSERT INTO testcase
		        (probid,rank,md5sum_input,md5sum_output,input,output,description,sample)
		        VALUES (%i,%i,%s,%s,%s,%s,%s,%i)",
		       $probid, $rank, md5(@$content['input']), md5(@$content['output']),
		       @$content['input'], @$content['output'], @$_POST['add_desc'],
		       @$_POST['add_sample']);

		if ( !empty($content['image']) ) {
			list($thumb, $type) = get_image_thumb_type($content['image']);

			$DB->q('UPDATE testcase SET image = %s, image_thumb = %s, image_type = %s
			        WHERE probid = %i AND rank = %i',
			       @$content['image'], $thumb, $type, $probid, $rank);
		}

		auditlog('testcase', $probid, 'added', "rank $rank");

		$result .= "<li>Added new testcase $rank from files " .
			htmlspecialchars($_FILES['add_input']['name']) .
			" (" . printsize($_FILES['add_input']['size']) . ") and " .
			htmlspecialchars($_FILES['add_output']['name']) .
			" (" . printsize($_FILES['add_output']['size']) . ").";
		if ( $_FILES['add_output']['size']>dbconfig_get('output_limit')*1024 ) {
			$result .= "<br /><b>Warning: output file size exceeds " .
			    "<code>output_limit</code> of " . dbconfig_get('output_limit') .
			    " kB. This will always result in wrong answers!</b>";
		}
		if ( empty($content['input']) || empty($content['output']) ) {
			$result .= "<br /><b>Warning: empty testcase file(s)!</b>";
		}
		$result .= "</li>\n";
	}
}
if ( !empty($result) ) {
	echo "<ul>\n$result</ul>\n\n";

	// Reload testcase data after updates
	get_testcase_data();
}

// Check if ranks must be renumbered (if test cases have been deleted).
// There is no need to run this within one MySQL transaction since
// nothing depends on the ranks being sequential, and we do preserve
// their order while renumbering.
end($data);
if ( count($data)<(int)key($data) ) {
	$newrank = 1;
	foreach( $data as $rank => $row ) {
		$DB->q('UPDATE testcase SET rank = %i
		        WHERE probid = %i AND rank = %i', $newrank++, $probid, $rank);
	}

	echo "<p>Test case rankings reordered.</p>\n\n";

	// Reload testcase data after updates
	get_testcase_data();
}

echo "<p><a href=\"problem.php?id=" . urlencode($probid) . "\">back to problem p" .
	htmlspecialchars($probid) . "</a></p>\n\n";

if ( IS_ADMIN ) {
	echo addForm($pagename, 'post', null, 'multipart/form-data') .
	    addHidden('probid', $probid);
}

if ( count($data)==0 ) {
	echo "<p class=\"nodata\">No testcase(s) yet.</p>\n";
} else {
	?>
<table class="list testcases">
<thead><tr>
<th scope="col">#</th><th scope="col">download</th>
<th scope="col">size</th><th scope="col">md5</th>
<?php
	if ( IS_ADMIN ) echo '<th scope="col">upload new</th>';
?><th scope="col">sample</th><th scope="col">description / image</th><th></th>
</tr></thead>
<tbody>
<?php
}

foreach( $data as $rank => $row ) {
	foreach($INOROUT as $inout) {
		echo '<tr' . ( $inout=='output' ? ' class="testcase-middle"' : '' ) . '>';
		if ( $inout=='input' ) {
			echo "<td rowspan=\"2\" class=\"testrank\">" .
			    "<a href=\"./testcase.php?probid=" . urlencode($probid) .
			    "&amp;rank=$rank&amp;move=up\">&uarr;</a>$rank" .
			    "<a href=\"./testcase.php?probid=" . urlencode($probid) .
			    "&amp;rank=$rank&amp;move=down\">&darr;</a></td>";
		}
		echo "<td class=\"filename\"><a href=\"./testcase.php?probid=" .
		    urlencode($probid) . "&amp;rank=$rank&amp;fetch=" . $inout . "\">" .
		    filebase($probid,$rank) . substr($inout,0,-3) . "</a></td>" .
		    "<td class=\"size\">" . printsize($row["size_$inout"]) . "</td>" .
		    "<td class=\"md5\">" . htmlspecialchars($row["md5sum_$inout"]) . "</td>";
		if ( IS_ADMIN ) {
		    echo "<td>" . addFileField("update_".$inout."[$rank]") . "</td>";
		}
		if ( $inout=='input' ) {
			if ( IS_ADMIN ) {
				echo "<td rowspan=\"2\"	class=\"testsample\" onclick=\"editTcSample($rank)\">" .
				    addSelect("sample[$rank]",array("no", "yes"), $row['sample'], true) . "</td>";

				// hide sample dropdown field if javascript is enabled
				echo "<script type=\"text/javascript\" language=\"JavaScript\">" .
				    "hideTcSample($rank, '". printyn($row['sample'])."');</script>";
				echo "<td class=\"testdesc\" onclick=\"editTcDesc($rank)\">" .
				    "<textarea id=\"tcdesc_$rank\" name=\"description[$rank]\" cols=\"50\" rows=\"1\">" .
				    htmlspecialchars($row['description']) . "</textarea></td>" .
				    "<td rowspan=\"2\" class=\"editdel\">" .
				    "<a href=\"delete.php?table=testcase&amp;testcaseid=$row[testcaseid]&amp;referrer=" .
				    urlencode('testcase.php?probid='.$probid) . "\">" .
				    "<img src=\"../images/delete.png\" alt=\"delete\"" .
				    " title=\"delete this testcase\" class=\"picto\" /></a></td>";
			} else {
				echo "<td rowspan=\"2\" align=\"testsample\">" .
					printyn($row['issample']) . "</td>";
				echo "<td class=\"testdesc\">" .
				    htmlspecialchars($row['description']) . "</td>";
			}
		} else {
			echo '<td class="testimage filename">';
			if ( $row['size_image'] ) {
				echo "<a href=\"./testcase.php?probid=" . urlencode($probid) .
				    "&amp;rank=$rank&amp;fetch=image\">" .
				    filebase($probid,$rank). $row['image_type'] . "</a>";
			} else {
				echo '<span class="nodata">No image.</span>';
			}
			if ( IS_ADMIN ) {
				echo '&nbsp;' . addFileField("update_image[$rank]");
			}
			echo "</td>";
		}
		echo "</tr>\n";
	}
}

if ( count($data)!=0 ) echo "</tbody>\n</table>\n";

if ( IS_ADMIN ) {
	echo "<script type=\"text/javascript\">\n";
	foreach ( $data as $rank => $row ) {
		echo "hideTcDescEdit($rank);\n";
	}
	echo "</script>\n\n";

?>
<h3>Create new testcase</h3>

<table>
<tr><td>Input testdata: </td><td><?php echo addFileField('add_input')  ?></td></tr>
<tr><td>Output testdata:</td><td><?php echo addFileField('add_output') ?></td></tr>
<tr><td>Sample testcase:</td><td><?php echo addSelect('add_sample', array("no","yes"), 0, true);?></td></tr>
<tr><td>Description:    </td><td><?php echo addInput('add_desc','',30); ?></td></tr>
<tr><td>Image:          </td><td><?php echo addFileField('add_image') ?></td></tr>
</table>
<?php

	echo "<br />" . addSubmit('Submit all changes') . addEndForm();
}

require(LIBWWWDIR . '/footer.php');
