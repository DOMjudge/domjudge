<?php
/**
 * View a problem
 *
 * $Id$
 */

$id = $_GET['id'];

require('init.php');
$refresh = '15;url=' . getBaseURI() . 'jury/problem.php?id=' . urlencode($id);
$title = 'Problem';
require('../header.php');
require('menu.php');


echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM problem NATURAL JOIN contest WHERE probid = %s', $id);

?>
<table>
<tr><td>ID:</td><td><?=htmlspecialchars($data['probid'])?></td></tr>
<tr><td>Name:</td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Contest:</td><td><?=htmlspecialchars($data['cid']).' - ' .htmlentities($data['contestname'])?></td></tr>
<tr><td>Allow submit:</td><td><?=printyn($data['allow_submit'])?></td></tr>
<tr><td>Allow judge:</td><td><?=printyn($data['allow_judge'])?></td></tr>
<tr><td>Testdata:</td><td class="filename"><?=htmlspecialchars($data['testdata'])?></td></tr>
<tr><td>Timelimit:</td><td><?=(int)$data['timelimit']?></td></tr>
</table>

<h2>Submissions for <?=htmlspecialchars($id)?></h2>

<?php
putSubmissions('probid', $id, TRUE);

require('../footer.php');
