<?php

/* PHP configuration file placeholder.
 *
 * This should be replaced by a configuration file generated from
 * 'global.conf'. See that file for more information.
 * This file generates an error when used, to prevent using an
 * unconfigured system.
 *
 * $Id$
 */

// is this the webinterface or commandline?
if ( isset($_SERVER['REMOTE_ADDR']) ) {
	echo "<fieldset class=\"error\"><legend>Error</legend>\n" .
		"DOMjudge is not configured yet: edit 'etc/global.cfg' and then run 'make config'." .
		"</fieldset>\n";
} else {
	echo "DOMjudge is not configured yet: edit 'etc/global.cfg' and then run 'make config'.\n";
}

exit(1);
