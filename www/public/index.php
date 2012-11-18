<?php
/**
 * Just show simple info message.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title="FAU online judge";

$menu = false;
require(LIBWWWDIR . '/header.php');

?>

<p><span>
If you already linked your <a href="https://est.informatik.uni-erlangen.de/">EST</a>
account to your FAU online judge account, you may <a
href="../team/">login</a> and start coding.
</span></p>

<p><span>
Otherwise you have to link your accounts first: <a href="https://icpc.informatik.uni-erlangen.de/register_oj.php">register</a><br/>
The registration page is only available using FAU IP addresses!
</span></p>

<?php

require(LIBWWWDIR . '/footer.php');
