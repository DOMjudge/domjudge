<?php
/**
 * Start of functionality to edit data from this interface.
 * Does not work yet.
 *
 * $Id$
 */
require('init.php');

$t = @$_REQUEST['table'];
if(!$t)	error ("No table selected.");

$l = (int)@$_REQUEST['add'];
if ( $l<1 ) $l = 1;

$title = 'Edit - '.$t;
require('../header.php');

if(isset($_REQUEST['submit'])) {
	edit_table($t);
}

echo "<h1>Edit - $t</h1>\n\n";

edit_table_show($t, $l);

require('../footer.php');


function edit_table($table)
{
	global $DB;
	
	$table = mysql_escape_string($table);
	
	$layout = $DB->q('TABLE SHOW COLUMNS FROM '.$table);
	
	if( is_array(@$_REQUEST['del']) )
	{
	}
	
	echo "<pre>";
	print_r($_REQUEST['new']);
	echo "---\n";
	print_r($_REQUEST['data']);
	echo "---\n";
	print_r($_REQUEST['old']);
	echo "---\n";
	print_r(@$_REQUEST['del']);
	echo "---\n";
	echo "</pre>";
}


function edit_table_show($table, $add_lines)
{
	global $DB;
	
	$table = mysql_escape_string($table);
	
	$layout = $DB->q('TABLE SHOW COLUMNS FROM '.$table);
	$data = $DB->q('TABLE SELECT * FROM ' . $table);
	
?>
<form action="edit.php" method="post">
<input type="hidden" name="table" value="<?=$table?>" />
<table>
	<tr>
<?

	foreach ($layout as $field)
	{
		echo "\t\t<th>".$field['Field']."</th>\n";
	}
	echo "\t\t<th>Delete</th>\n";

	echo "\t</tr>";
	
	$i = 0;
	foreach ($data as $row)
	{
		echo "\t<tr>\n";
		foreach ($layout as $field)
		{
			$f = $field['Field'];
			
			echo "\t\t<td>\n";
			
			echo "<input type=\"hidden\" name=\"old[$i][$f]\" value=\"".$row[$f]."\" />\n";
			
			if($field['Extra'] == 'auto_increment') {
				echo htmlentities($row[$f]);
			} else {
				echo "<input type=\"text\" name=\"data[$i][$f]\" value=\"".$row[$f]."\" />\n";
			}
			
			echo "</td>\n";
		}
		echo "\t\t<td><input type=\"checkbox\" name=\"del[$i]\" /></td>\n";
		echo "\t</tr>\n";

		$i++;
	}
	
	for($i = 0; $i < $add_lines; $i++)
	{
		echo "\t<tr>\n";
		foreach ($layout as $field)
		{
			$f = $field['Field'];
			
			echo "\t\t<td>\n";
			
			if($field['Extra'] == 'auto_increment') {
				echo "**";
			} else {
				echo "<input type=\"text\" name=\"new[$i][$f]\" />\n";
			}
		}
		echo "\t</tr>\n";
	}
?>
</table>
<input type="Reset" /><br />
<input type="Submit" name="submit" value="Edit" />
</form>
<pre>
<?=print_r($layout)?>
<?=print_r($data)?>
</pre>
<?
}
