<?php
require("init.php");

if( ! isset($_GET['id'])) {
	echo "There is an error in request";
	exit;
}
$id = $_GET['id'];
//Avoidance for SQL injection and erroneous/spurius requests
if(!is_numeric($id)) {
	echo "There is an error in request";
	exit;
}
$submission = $DB->q("MAYBETUPLE SELECT * FROM submission s WHERE submitid = %i", $id);
if(empty($submission)) error ("Submission $id not found");
if($teamid != $submission['teamid']) {
	echo "There is an error in request";
	exit;
}
$row = $DB->q('TUPLE SELECT filename, sourcecode FROM submission_file WHERE submitid = %i', $id);
header("Content-Type: text/plain; name=\"" . $row['filename'] . "\"; charset=" . DJ_CHARACTER_SET);
header("Contest-Disposition: attachment; filename=\"" . $row['filename'] . "\"");
header("Content-Length: " . strlen($row['sourcecode']));
echo $row['sourcecode'];
exit;
?>

