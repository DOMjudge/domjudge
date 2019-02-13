<?php declare(strict_types=1);
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Import / Export';
require(LIBWWWDIR . '/header.php');

requireAdmin();

?>
<h1>Import and Export</h1>

<h2>Import / Export via file down/upload</h2>

<ul>
<li><a href="impexp_contestyaml.php">Contest data (contest.yaml)</a></li>
</ul>

<?php

require(LIBWWWDIR . '/footer.php');
