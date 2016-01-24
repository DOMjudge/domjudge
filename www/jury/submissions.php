<?php
/**
 * View the submissionqueue
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'all');

$view = 0;

// Restore most recent view from cookie (overridden by explicit selection)
if ( isset($_COOKIE['domjudge_submissionview']) && isset($viewtypes[$_COOKIE['domjudge_submissionview']]) ) {
	$view = $_COOKIE['domjudge_submissionview'];
}

if ( isset($_REQUEST['view']) ) {
	// did someone press any of the four view buttons?
	foreach ($viewtypes as $i => $name) {
		if ( isset($_REQUEST['view'][$i]) ) $view = $i;
	}
}

require('init.php');
$refresh = array(
	'after' => 15,
	'url' => 'submissions.php?' .
		urlencode('view[' . $view . ']') . '=' . urlencode($viewtypes[$view])
);
$title = 'Submissions';

// Set cookie of submission view type, expiry defaults to end of session.
dj_setcookie('domjudge_submissionview', $view);

$jury_member = $username;

$jqtokeninput = true;

require(LIBWWWDIR . '/header.php');

echo "<h1>$title</h1>\n\n";

$restrictions = array();
if ( $viewtypes[$view] == 'unverified' ) $restrictions['verified'] = 0;
if ( $viewtypes[$view] == 'unjudged' ) $restrictions['judged'] = 0;

echo addForm($pagename, 'get') . "<p>Show submissions:\n";
for( $i=0; $i<count($viewtypes); ++$i ) {
	echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
echo "</p>\n" . addEndForm();

echo "<div class='submissions-filter'><p>Filter submissions on:</p>\n";

$filters = array(
	'problem' => array(
		'ajax' => 'ajax_problems.php',
		'hintText' => 'Type to search for problem ID or name',
		'noResultsText' => 'No problems found',
		'prePopulateQuery' => "TABLE SELECT probid AS id, CONCAT(name, ' (p', probid, ')') AS search
		     FROM problem WHERE probid IN (%Ai)",
	),
	'language' => array(
		'ajax' => 'ajax_languages.php',
		'hintText' => 'Type to search for language ID or name',
		'noResultsText' => 'No languages found',
		'prePopulateQuery' => "TABLE SELECT langid AS id, name,
		    CONCAT(name, ' (', langid, ')') AS search FROM language
		    WHERE langid IN (%As)",
	),
	'team' => array(
		'ajax' => 'ajax_teams.php',
		'hintText' => 'Type to search for team ID or name',
		'noResultsText' => 'No teams found',
		'prePopulateQuery' => "TABLE SELECT teamid AS id, name,
		 CONCAT(name, ' (t', teamid, ')') AS search FROM team
		 WHERE teamid IN (%Ai)",
	),
);
$submissions_filter = array();
if ( isset($_COOKIE['submissions-filter']) ) {
	$submissions_filter = json_decode($_COOKIE['submissions-filter'], true);
}
foreach ( $filters as $filter_name => $filter_data ) {
	$prepopulate = array();
	if ( isset($submissions_filter[$filter_name . '-id']) ) {
		$prepopulate = $DB->q($filter_data['prePopulateQuery'], $submissions_filter[$filter_name . '-id']);
	}
?>
<p>
	<span><?php echo ucfirst($filter_name); ?>(s)</span>
	<input data-filter-field="<?php echo $filter_name; ?>-id" class="filter" id="filter_<?php echo $filter_name; ?>" size="50" type="text" />
</p>
<script type="text/javascript">
	$(function() {
		$('#filter_<?php echo $filter_name; ?>').tokenInput('<?php echo $filter_data['ajax']; ?>', {
			propertyToSearch: 'search',
			hintText: '<?php echo $filter_data['hintText']; ?>',
			noResultsText: '<?php echo $filter_data['noResultsText']; ?>',
			preventDuplicates: true,
			excludeCurrent: true,
			prePopulate: <?php echo json_encode($prepopulate); ?>
		});
	});
</script>
<?php
}
?>
</div>
<script type="text/javascript">
$(function() {
	var process_submissions_filter = function () {
		var filters = [];

		$('input[data-filter-field]').each(function () {
			var $filter_field = $(this);
			if ($filter_field.val() != '') {
				filters.push({
					field: $filter_field.data('filter-field'),
					values: $filter_field.val().split(',')
				});
			}
		});

		var submissions_filter = {};
		for ( var i = 0; i < filters.length; i++ ) {
			submissions_filter[filters[i].field] = filters[i].values;
		}

		Cookies.set('submissions-filter', submissions_filter);

		var $trs = $('.submissions > tbody tr');

		if (filters.length == 0) {
			$trs.show();
		} else {
			$trs
				.hide()
				.filter(function () {
					var $tr = $(this);

					for (var i = 0; i < filters.length; i++) {
						var value = "" + $tr.data(filters[i].field);
						if (filters[i].values.indexOf(value) == -1) {
							return false;
						}
					}

					return true;
				})
				.show();
		}
	};

	var $filter = $('.filter');

	$filter.on('change', process_submissions_filter);

	console.log($filter.parent().find('input[type=text]'));

	var refreshWasEnabled = false;

	$filter.parent().find('input[type=text]').on('focus', function() {
		refreshWasEnabled = refreshEnabled;
		if (refreshEnabled) {
			$('#refresh-toggle').attr('disabled', 'disabled');
			disableRefresh();
		}
	});

	$filter.parent().find('input[type=text]').on('blur', function() {
		if (refreshWasEnabled && !refreshEnabled) {
			$('#refresh-toggle').attr('disabled', null);
			enableRefresh();
		}
	});

	process_submissions_filter();
});
</script>
<?php

$contests = $cdatas;
if ( $cid !== null ) {
	$contests = array($cid => $cdata);
}

putSubmissions($contests, $restrictions, ($viewtypes[$view] == 'newest' ? 50 : 0));

require(LIBWWWDIR . '/footer.php');
