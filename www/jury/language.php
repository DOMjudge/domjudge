<?php
/**
 * View a language
 *
 * $Id$
 */

require('init.php');
$title = 'Language';
require('../header.php');

$id = $_GET['id'];

echo "<h1>Language ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s', $id);

?>
<table>
<tr><td>ID:</td><td><?=$data['langid']?></td></tr>
<tr><td>Name:</td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Extension:</td><td><tt>.<?=$data['extension']?></tt></td></tr>
<tr><td>Allow judge:</td><td><?=$data['allow_judge']?></td></tr>
<tr><td>Timefactor:</td><td><tt><?=$data['time_factor']?></tt></td></tr>
</table>

<h2>Submissions in <?=htmlspecialchars($id)?></h2>

<?php
getSubmissions('langid', $id);

require('../footer.php');
