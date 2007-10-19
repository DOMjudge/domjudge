#!/usr/bin/php -q
<?php
/**
 * Output public scoreboard static HTML to standard out.
 * This is basically a snapshot of the public scoreboard in the
 * DOMjudge web interface, but without automatic refresh, and
 * with no links to subpages. It does depend on the style.css.
 *
 * Use this when you want to generate a HTML page for the public
 * scoreboard, e.g. for the contest final results, or to use as
 * a very scalable public view. In the latter case, do something
 * like this:
 *
 * while true; do ./static_scoreboard.php > scores.html; sleep 30; done
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( isset($_SERVER['REMOTE_ADDR']) )
	die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'static_scoreboard');

chdir(SYSTEM_ROOT . '/www/public/');

passthru("php ./index.php static");

