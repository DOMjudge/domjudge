<?php
/**
 * View a problem
 *
 * $Id$
 */

require('init.php');
$title = 'Problem';
require('../header.php');

$id = $_GET['id'];

echo "<h1>Problem ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM problem WHERE probid = %s', $id);

?>
<table>
<tr><td>ID:</td><td><?=$data['probid']?></td></tr>
<tr><td>Name:</td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Allow submit:</td><td><?=$data['allow_submit']?></td></tr>
<tr><td>Allow judge:</td><td><?=$data['allow_judge']?></td></tr>
<tr><td>Testdata:</td><td><tt><?=$data['testdata']?></tt></td></tr>
<tr><td>Timelimit:</td><td><?=$data['timelimit']?></td></tr>
</table>

<h2>Submissions for <?=htmlspecialchars($id)?></h2>

<?php
getSubmissions('probid', $id);

require('../footer.php');
