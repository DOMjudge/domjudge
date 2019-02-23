<?php declare(strict_types=1);
/**
 * Upload form for documents to be sent to the printer.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Print';
require(LIBWWWDIR . '/header.php');

echo "<h1>Print source</h1>\n\n";

if (! have_printing()) {
    error("Printing disabled.");
}

// Seems reasonable to require that there's a contest running
// before allowing to submit printouts.
$fdata = calcFreezeData($cdata);
if (!checkrole('jury') && !$fdata['started']) {
    echo "<div class=\"alert alert-secondary\">Contest has not yet started.</div>\n";
    require(LIBWWWDIR . '/footer.php');
    return;
}

if (isset($_POST['langid'])) {
    handle_print_upload();
} else {
    put_print_form();
}

require(LIBWWWDIR . '/footer.php');
