<?php
/**
 * $Id$
 */

require('init.php');
$title = 'Jury interface';
require('../header.php');

echo "<h1>Jury interface</h1>\n\n";
?>
<ul>
<li><a href="submissions.php">Submissions</a>
<li><a href="teams.php">Teams</a>
</ul>

<p>
<?php
$cont = $DB->q('TUPLE SELECT * FROM contest');
echo "<table>
<tr><td>Start:</td><td>$cont[starttime]</td></tr>
<tr><td>End:</td><td>$cont[endtime]</td></tr>
<tr><td>Now:</td><td>".date('Y-m-d H:i:s').
"</table>\n\n";

require('../footer.php');
