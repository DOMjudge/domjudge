<?php
/**
 * Import/export configration settings to and from contest.yaml.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

requireAdmin();

require(LIBEXTDIR . '/spyc/spyc.php');

if ( isset($_POST['import']) ) {

	if ( isset($_FILES) && isset($_FILES['import_config']) && isset($_FILES['import_config']['name']) && isset($_FILES['import_config']['tmp_name']) ) {

		$file = $_FILES['import_config']['name'];

		$contest_yaml_data = Spyc::YAMLLoad($_FILES['import_config']['tmp_name']);

		if ( empty($contest_yaml_data) ) {
			echo "<p>Error parsing YAML file.</p>\n";
			require(LIBWWWDIR . '/footer.php');
			exit;
		}

		require(LIBWWWDIR . '/checkers.jury.php');

		$contest = array();
		$contest['contestname'] = $contest_yaml_data['name'];
		$contest['starttime'] = strftime(MYSQL_DATETIME_FORMAT, strtotime($contest_yaml_data['start-time']));
		$contest['activatetime'] = '-1:00';
		$contest['endtime'] = '+' . $contest_yaml_data['duration'];
		if ( ! empty($contest_yaml_data['scoreboard-freeze']) ) {
			$contest['freezetime'] = '+' . $contest_yaml_data['scoreboard-freeze'];
		}
		// TODO or 1?
		$contest['enabled'] = 0;

		$contest = check_contest($contest);

		$cid = $DB->q("RETURNID INSERT INTO contest SET %S", $contest);

		if ( ! empty($CHECKER_ERRORS) ) {
			echo "<p>Contest data not valid:</p>\n";
			echo "<ul>\n";
			foreach ($CHECKER_ERRORS as $error) {
				echo "<li>" . $error . "</li>\n";
			}
			echo "</ul>\n";
			require(LIBWWWDIR . '/footer.php');
			exit;

		}

		dbconfig_init();

		// TODO: event-feed-port

		$LIBDBCONFIG['penalty_time']['value'] = $contest_yaml_data['penaltytime'];
		$LIBDBCONFIG['clar_answers']['value'] = $contest_yaml_data['default-clars'];
		$categories = array();
		foreach ( $contest_yaml_data['clar-categories'] as $category ) {
			$cat_key = substr(str_replace(array(' ', ',', '.'), '-', strtolower($category)), 0, 9);
			$categories[$cat_key] = $category;
		}
		$LIBDBCONFIG['clar_categories']['value'] = $categories;

		$DB->q("DELETE FROM language");
		foreach ($contest_yaml_data['languages'] as $language) {
			$lang = array();
			// TODO better lang-id?
			$lang['langid'] = str_replace(array('+', '#', ' '), array('p', 'sharp', '-'), strtolower($language['name']));
			$lang['name'] = $language['name'];
			$lang['allow_submit'] = 1;
			$lang['allow_judge'] = 1;
			$lang['time_factor'] = 1;

			$DB->q("INSERT INTO language SET %S", $lang);
		}

		foreach ($contest_yaml_data['problemset'] as $problem) {
			// TODO better lang-id?
			$prob = array();
			if ( $DB->q("MAYBEVALUE SELECT probid FROM problem WHERE probid = %s", $problem['letter']) ) {
				echo "<p>A problem with problem id $problem[letter] already exists.</p>\n";
				require(LIBWWWDIR . '/footer.php');
				exit;
			}
			$prob['probid'] = $problem['letter'];
			$prob['cid'] = $cid;
			$prob['name'] = $problem['short-name'];
			$prob['allow_submit'] = 1;
			$prob['allow_judge'] = 1;
			// TODO Fredrik?
			$prob['timelimit'] = 10;
			$prob['color'] = $pbolem['rgb'];

			$DB->q("INSERT INTO problem SET %S", $prob);
		}

		dbconfig_store();

		// Redirect to the original page to prevent accidental redo's
		header('Location: import-export-config.php?import-ok&file='.$file);

	} else {

		echo "<p>Error uploading file.</p>\n";
		require(LIBWWWDIR . '/footer.php');
		exit;

	}

} elseif ( isset($_POST['export']) ) {

	// Fetch data from database and store in an associative array
	$cid = @$_POST['contest'];

	$contest_row = $DB->q("MAYBETUPLE SELECT * FROM contest WHERE cid = %i", $cid);

	if ( ! $contest_row ) {
		echo "<p>Contest not found.</p>\n";
		require(LIBWWWDIR . '/footer.php');
		exit;
	}
	$res = $DB->q('KEYTABLE SELECT *, intervalid AS ARRAYKEY
	               FROM removed_interval WHERE cid = %i', $cid);

	$contest_row['removed_intervals'] = $res;

	$contest_data = array();
	$contest_data['name'] = $contest_row['contestname'];
	$contest_data['short-name'] = $contest_row['contestname'];
	$contest_data['start-time'] = date('c', strtotime($contest_row['starttime']));
	$contest_data['duration'] = printtimerel(calcContestTime($contest_row['endtime'], $contest_row));

	if ( ! is_null($contest_row['freezetime']) ) {
		$contest_data['scoreboard-freeze'] = printtimerel(calcContestTime($contest_row['freezetime'], $contest_row));
	}

	// TODO: event-feed-port
	$contest_data['penaltytime'] = dbconfig_get('penalty_time');
	$contest_data['default-clars'] = dbconfig_get('clar_answers');
	$contest_data['clar-categories'] = array_values(dbconfig_get('clar_categories'));
	$contest_data['languages'] = array();
	$q = $DB->q("SELECT * FROM language");
	while ( $lang = $q->next() ) {

		$language = array();
		$language['name'] = $lang['name'];
		// TODO: compiler, -flags, runner, -flags?
		$contest_data['languages'][] = $language;

	}
	$contest_data['problemset'] = array();
	$q = $DB->q("SELECT * FROM problem WHERE cid = %i", $cid);
	while ( $prob = $q->next() ) {

		$problem = array();
		$problem['letter'] = $prob['probid'];
		$problem['short-name'] = $prob['name'];
		$problem['color'] = $prob['color'];
		// TODO? rgb? Fredrik?
		$contest_data['problemset'][] = $problem;

	}


	$yaml = Spyc::YAMLDump($contest_data);

	echo $yaml;
	header('Content-type: text/x-yaml');
	header('Content-Disposition: attachment; filename="contest.yaml"');
	exit;

}

$title = "Import / export configuration";
require(LIBWWWDIR . '/header.php');

echo "<h1>Import / export configuration</h1>\n\n";

if ( isset($_GET['import-ok']) ) {
	echo msgbox("Import successful!", "The file " . htmlspecialchars(@$_GET['file']) . " is successfully imported.");
}

echo "<h2>Import from YAML</h2>\n\n";
echo addForm('import-export-config.php', 'post', null, 'multipart/form-data');
echo msgbox("Please note!", "Importing a contest.yaml will overwrite some settings (e.g. penalty time, clarification categories, clarification answers, etc.). This action can not be undone!");
echo addFileField('import_config');
echo addSubmit('Import', 'import') . addEndForm();
echo "<h2>Export to YAML</h2>\n\n";
echo addForm('import-export-config.php');
echo '<label for="contest">Select contest: </label>';
$contests = $DB->q("KEYVALUETABLE SELECT cid, contestname FROM contest");
echo addSelect('contest', $contests, null, true);
echo addSubmit('Export', 'export') . addEndForm();


require(LIBWWWDIR . '/footer.php');
