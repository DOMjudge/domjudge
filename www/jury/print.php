<?php
/**
 * Upload form for documents to be sent to the printer.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$title = 'Print';
require(LIBWWWDIR . '/header.php');

echo "<h1>Print source</h1>\n\n";

if ( ! have_printing() ) {
	error("Printing disabled.");
}

if ( isset($_POST['langid']) ) {
	handle_print_upload();
} else {
	put_print_form();
}

require(LIBWWWDIR . '/footer.php');
