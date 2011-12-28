<?php
/**
 * Edit clarification categories and default answers
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$pagename = basename($_SERVER['PHP_SELF']);

require('init.php');

require_once(LIBWWWDIR . '/clarification.php');

$categs = getClarCategories();

$answers = getClarAnswers();

// Reorder categories and answers
if ( isset($_GET['move']) ) {
	$move = $_GET['move'];
	$rank = $_GET['rank'];

	switch ( $_GET['type'] ) {
	case 'category':
		$data = $categs;
		$rank = '#'.$rank;
		break;
	case 'answer':
	    $data = $answers;
		break;
	default:
		error("Invalid data type '".$_GET['type']."'.");
	}

	// First find entry to switch with
	$last = NULL;
	$other = NULL;
	$newdata = array();
	foreach( $data as $curr => $val ) {
		if ( $rank==$curr && $move=='up' && $last!==NULL ) {
			$other = $last;
			array_pop($newdata);
			$newdata[$curr] = $val;
			$newdata[$last] = $data[$last];
		}
		elseif ( $rank==$last && $move=='down' ) {
			$other = $curr;
			array_pop($newdata);
			$newdata[$curr] = $val;
			$newdata[$last] = $data[$last];
		}
		else {
			$newdata[$curr] = $val;
		}
		$last = $curr;
	}

	if ( $other!==NULL ) {
		switch ( $_GET['type'] ) {
		case 'category': setClarCategories($newdata); break;
		case 'answer':   setClarAnswers($newdata);   break;
		}
		auditlog('configuration', NULL, 'reorder clar_'.$_GET['type'], "$rank <=> $other");
	}

	// Redirect to the original page to prevent accidental redo's
	header('Location: '.$pagename);
	return;
}

// Add new entries, replace tabs by spaces since these are used as
// internal storage separators.
if ( isset($_POST['add']) ) {
	if ( !empty($_POST['categ_id']) ) {
		$rank = str_replace("\t", ' ', $_POST['categ_id']);
		$desc = str_replace("\t", ' ', $_POST['categ_desc']);
		$id = '#'.$rank;
		$categs[$id] = $desc;
		setClarCategories($categs);
		auditlog('configuration', NULL, 'add clar_category', $rank);
	}
	if ( !empty($_POST['answer_desc']) ) {
		$desc = str_replace("\t", ' ', $_POST['answer_desc']);
		$answers[] = $desc;
		setClarAnswers($answers);
		auditlog('configuration', NULL, 'add clar_answer',
		         summarizeClarification($desc));
	}

	// Redirect to the original page to prevent accidental redo's
	header('Location: '.$pagename);
	return;
}

// Delete entry
if ( isset($_GET['delete']) ) {
	$rank = $_GET['rank'];
	switch ( $_GET['type'] ) {
	case 'category':
		$id = '#'.$rank;
		if ( !isset($categs[$id]) ) error("Entry '$rank' not found in categories.");
		$newdata = array_diff_key($categs, array($id => ''));
		setClarCategories($newdata);
		var_dump($rank, $newdata);
		auditlog('configuration', NULL, 'delete clar_category', $rank);
		break;
	case 'answer':
		if ( !isset($answers[$rank]) ) error("Entry '$rank' not found in answers.");
		$newdata = array_diff_key($answers, array($_GET['rank'] => ''));
		setClarAnswers($newdata);
		auditlog('configuration', NULL, 'delete clar_answer',
		         summarizeClarification($answers[$rank]));
		break;
	default:
		error("Invalid data type '".$_GET['type']."'.");
	}

	// Redirect to the original page to prevent accidental redo's
	header('Location: '.$pagename);
	return;
}

$title = 'Clarification config';

require(LIBWWWDIR . '/header.php');

requireAdmin();

echo "<h1>" . $title ."</h1>\n\n";

echo "<p><em>First entries are used as default.</em></p>\n";

echo addForm('', 'post', null, 'multipart/form-data');

	?>
<h2>Clarification categories</h2>

<table class="list testcases">
<thead><tr>
<th scope="col">ID</th><th scope="col">description</th>
</tr></thead>
<tbody>
<?php

foreach( $categs as $key => $desc ) {
	$rank = substr($key, 1);
	echo "<tr><td class=\"testrank\">" .
		"<a href=\"./clar_config.php?rank=".urlencode($rank)."&amp;move=up&amp;" .
		"type=category\">&uarr;</a>" . htmlspecialchars($rank) .
		"<a href=\"./clar_config.php?rank=".urlencode($rank)."&amp;move=down&amp;" .
		"type=category\">&darr;</a></td>" .
		"<td><pre>" . htmlspecialchars($desc) . "</pre></td>" .
		"<td class=\"editdel\">" .
		"<a href=\"./clar_config.php?rank=$rank&amp;delete&amp;type=category\">" .
		"<img src=\"../images/delete.png\" alt=\"delete\"" .
		" title=\"delete this row\" class=\"picto\" /></a></td></tr>\n";
}

echo '<tr><td>' . addInput('categ_id', '', 10) .
	'</td><td>' . addTextArea('categ_desc', '', 45, 2) . "</td></tr>\n";

	?>
</tbody></table>

<h2>Clarification answers</h2>

<table class="list testcases">
<thead><tr>
<th scope="col">#</th><th scope="col">description</th><th></th>
</tr></thead>
<tbody>
<?php

foreach( $answers as $rank => $desc ) {
	echo "<tr><td class=\"testrank\">" .
		"<a href=\"./clar_config.php?rank=".urlencode($rank)."&amp;move=up&amp;" .
		"type=answer\">&uarr;</a>" . htmlspecialchars($rank) .
		"<a href=\"./clar_config.php?rank=".urlencode($rank)."&amp;move=down&amp;" .
		"type=answer\">&darr;</a></td>" .
		"<td><pre>" . htmlspecialchars($desc) . "</pre></td>" .
		"<td class=\"editdel\">" .
		"<a href=\"./clar_config.php?rank=$rank&amp;delete&amp;type=answer\">" .
		"<img src=\"../images/delete.png\" alt=\"delete\"" .
		" title=\"delete this row\" class=\"picto\" /></a></td></tr>\n";
}

echo '<tr><td></td><td>' . addTextArea('answer_desc', '', 55, 3) .
	"</td></tr>\n</tbody></table>\n";

echo "<br />" . addSubmit('Save new entries', 'add') . addEndForm();

require(LIBWWWDIR . '/footer.php');
