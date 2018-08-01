<?php
/**
 * View the submissionqueue
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$viewtypes = array(0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'all');

$view = 0;

// Restore most recent view from cookie (overridden by explicit selection)
if (isset($_COOKIE['domjudge_submissionview']) && isset($viewtypes[$_COOKIE['domjudge_submissionview']])) {
    $view = $_COOKIE['domjudge_submissionview'];
}

if (isset($_REQUEST['view'])) {
    // did someone press any of the four view buttons?
    foreach ($viewtypes as $i => $name) {
        if (isset($_REQUEST['view'][$i])) {
            $view = $i;
        }
    }
}

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
if ($viewtypes[$view] == 'unverified') {
    $restrictions['verified'] = 0;
}
if ($viewtypes[$view] == 'unjudged') {
    $restrictions['judged'] = 0;
}

echo addForm($pagename, 'get') . "<p>Show submissions:\n";
for ($i=0; $i<count($viewtypes); ++$i) {
    echo addSubmit($viewtypes[$i], 'view['.$i.']', null, ($view != $i));
}
echo "</p>\n" . addEndForm();

$submissions_filter = array();
if (isset($_COOKIE['submissions-filter'])) {
    $submissions_filter = dj_json_decode($_COOKIE['submissions-filter']);
}

echo "<a class=\"collapse\" href=\"javascript:collapse('submissions-filter')\"><img src=\"../images/filter.png\" alt=\"filter&hellip;\" title=\"filter&hellip;\" class=\"picto\" /></a>";
echo "<div id='detailsubmissions-filter' class='submissions-filter'>\n";

if (empty($submissions_filter)) {
    echo <<<HTML
<script type="text/javascript">
<!--
collapse("submissions-filter");
// -->
</script>
HTML;
}

$filters = array(
    'problem' => array(
        'ajax' => $cid ? ('ajax_problems.php?fromcontest=' . $cid) : 'ajax_problems.php',
        'hintText' => 'Type to search for problem ID or name',
        'noResultsText' => 'No problems found',
        'prePopulateQuery' => $cid ?
            ("TABLE SELECT problem.probid AS id,
             CONCAT(problem.name, ' (', contestproblem.shortname, ' - p', problem.probid, ')') AS search
             FROM problem INNER JOIN contestproblem ON contestproblem.probid = problem.probid
             WHERE problem.probid IN (%Ai) AND contestproblem.cid = %i")
            :
            "TABLE SELECT probid AS id, CONCAT(name, ' (p', probid, ')') AS search
             FROM problem WHERE probid IN (%Ai) %_",
    ),
    'language' => array(
        'ajax' => 'ajax_languages.php?enabled=1',
        'hintText' => 'Type to search for language ID or name',
        'noResultsText' => 'No languages found',
        'prePopulateQuery' => "TABLE SELECT langid AS id, name,
            CONCAT(name, ' (', langid, ')') AS search FROM language
            WHERE langid IN (%As) %_",
    ),
    'team' => array(
        'ajax' => 'ajax_teams.php?enabled=1',
        'hintText' => 'Type to search for team ID or name',
        'noResultsText' => 'No teams found',
        'prePopulateQuery' => "TABLE SELECT teamid AS id, name,
         CONCAT(name, ' (t', teamid, ')') AS search FROM team
         WHERE teamid IN (%Ai) %_",
    ),
);
foreach ($filters as $filter_name => $filter_data) {
    $prepopulate = array();
    if (isset($submissions_filter[$filter_name . '-id'])) {
        $prepopulate = $DB->q($filter_data['prePopulateQuery'], $submissions_filter[$filter_name . '-id'], $cid);
    } ?>
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
    <input id="submissions-filter-clear-all" type="button" value="Clear all" />
</div>
<script type="text/javascript">
$(function() {
    $('#submissions-filter-clear-all').on('click', function() {
        $('.filter').tokenInput('clear').trigger('change');
        setTimeout(function() {
            $('.filter').parent().find('input').blur();
        }, 100);
    });

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

        var $trs = $('table > tbody tr');

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
if ($cid !== null) {
    $contests = array($cid => $cdata);
}

putSubmissions($contests, $restrictions, ($viewtypes[$view] == 'newest' ? 50 : 0), null, true);

require(LIBWWWDIR . '/footer.php');
