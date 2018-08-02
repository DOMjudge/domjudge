<?php
/**
 * View of one contest.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/checkers.jury.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : specialchars($_GET['cmd']) . ' ') .
                 'contest' . ($id ? ' c'.specialchars(@$id) : ''));

$jscolor=true;
$jqtokeninput = true;

require(LIBWWWDIR . '/header.php');

if (!empty($_GET['cmd'])):

    requireAdmin();

    $cmd = $_GET['cmd'];

    echo "<h2>$title</h2>\n\n";

    echo addForm('edit.php');

    echo "<table>\n";

    if ($cmd == 'edit') {
        $row = $DB->q('MAYBETUPLE SELECT * FROM contest WHERE cid = %i', $id);
        if (!$row) {
            error("Missing or invalid contest id");
        }

        echo "<tr><td>Contest ID:</td><td>" .
            addHidden('keydata[0][cid]', $row['cid']) .
            'c' . specialchars($row['cid']) .
            "</td></tr>\n";
    }
?>

<tr><td><label for="data_0__shortname_">Short name:</label></td>
<td colspan="2"><?php echo addInput('data[0][shortname]', @$row['shortname'], 40, 10, 'required')?></td></tr>
<tr><td><label for="data_0__name_">Contest name:</label></td>
<td colspan="2"><?php echo addInput('data[0][name]', @$row['name'], 40, 255, 'required')?></td></tr>
<tr><td><label for="data_0__activatetime_string_">Activate time:</label></td>
<td><?php echo addInput('data[0][activatetime_string]', (empty($row['activatetime_string'])?strftime('%Y-%m-%d %H:%M:00 ').date_default_timezone_get():$row['activatetime_string']), 30, 64, 'required pattern="' . $pattern_dateorneg . '"')?></td>
<td rowspan="6">
<b>Specification of contest times:</b><br />
Each of the contest times can be specified as absolute time or relative<br />
to the start time (except for start time itself). Use up to 6 subsecond<br />
decimals and a timezone from the
<a target="_blank" href="https://en.wikipedia.org/wiki/List_of_tz_database_time_zones">
time zone database</a>.
<br /><br />
Absolute time format: <b><kbd><?php echo $human_abs_datetime ?></kbd></b>
<a target="_blank" href="https://en.wikipedia.org/wiki/List_of_tz_database_time_zones">
<img src="../images/b_help.png" class="smallpicto" alt="?"></a><br />
Relative time format: <b><kbd><?php echo $human_rel_datetime ?></kbd></b><br />
</td></tr>

<tr><td><label for="data_0__starttime_string_">Start time:</label></td>
<td><?php echo addInput('data[0][starttime_string]', @$row['starttime_string'], 30, 64, 'required pattern="' . $pattern_datetime . '"')?></td></tr>

<tr><td>Start time enabled:</td><td>
<?php echo addRadioButton('data[0][starttime_enabled]', (!isset($row['starttime_enabled']) ||  $row['starttime_enabled']), 1)?> <label for="data_0__starttime_enabled_1">yes</label>
<?php echo addRadioButton('data[0][starttime_enabled]', (isset($row['starttime_enabled']) && !$row['starttime_enabled']), 0)?> <label for="data_0__starttime_undefined_0">no</label></td><td></td></tr>

<tr><td><label for="data_0__freezetime_string_">Scoreboard freeze time:</label></td>
<td><?php echo addInput('data[0][freezetime_string]', @$row['freezetime_string'], 30, 64, 'pattern="' . $pattern_dateorpos . '"')?></td></tr>

<tr><td><label for="data_0__endtime_string_">End time:</label></td>
<td><?php echo addInput('data[0][endtime_string]', @$row['endtime_string'], 30, 64, 'required pattern="' . $pattern_dateorpos . '"')?></td></tr>

<tr><td><label for="data_0__unfreezetime_string_">Scoreboard unfreeze time:</label></td>
<td><?php echo addInput('data[0][unfreezetime_string]', @$row['unfreezetime_string'], 30, 64, 'pattern="' . $pattern_dateorpos . '"')?></td></tr>

<tr><td><label for="data_0__deactivatetime_string_">Deactivate time:</label></td>
<td><?php echo addInput('data[0][deactivatetime_string]', @$row['deactivatetime_string'], 30, 64, 'pattern="' . $pattern_dateorpos . '"')?></td></tr>

<tr><td>Process balloons:</td><td>
<?php echo addRadioButton('data[0][process_balloons]', (!isset($row['process_balloons']) ||  $row['process_balloons']), 1)?> <label for="data_0__process_balloons_1">yes</label>
<?php echo addRadioButton('data[0][process_balloons]', (isset($row['process_balloons']) && !$row['process_balloons']), 0)?> <label for="data_0__process_balloons_0">no</label></td><td></td></tr>

<tr><td>Public:</td><td>
<?php echo addRadioButton('data[0][public]', (!isset($row['public']) ||  $row['public']), 1)?> <label for="data_0__public_1">yes</label>
<?php echo addRadioButton('data[0][public]', (isset($row['public']) && !$row['public']), 0)?> <label for="data_0__public_0">no</label></td><td></td></tr>

<tr id="teams" <?php if (!isset($row['public']) || $row['public']): ?>style="display: none; "<?php endif; ?>>
    <td>Teams:</td>
    <td>
<?php
    $prepopulate = $DB->q("TABLE SELECT teamid AS id, name,
                           CONCAT(name, ' (t', teamid, ')') AS search
                           FROM team INNER JOIN contestteam USING (teamid)
                           WHERE cid = %i", $id);

?>
        <?php echo addInput('data[0][mapping][1][items]', '', 50); ?>
        <script type="text/javascript">
            $(function() {
                $('#data_0__mapping__1__items_').tokenInput('ajax_teams.php', {
                    propertyToSearch: 'search',
                    hintText: 'Type to search for team ID or name',
                    noResultsText: 'No teams found',
                    preventDuplicates: true,
                    excludeCurrent: true,
                    prePopulate: <?php echo json_encode($prepopulate); ?>
                });
            });
        </script>
    </td><td></td>
</tr>

<tr><td>Enabled:</td><td>
<?php echo addRadioButton('data[0][enabled]', (!isset($row['enabled']) ||  $row['enabled']), 1)?> <label for="data_0__enabled_1">yes</label>
<?php echo addRadioButton('data[0][enabled]', (isset($row['enabled']) && !$row['enabled']), 0)?> <label for="data_0__enabled_0">no</label></td><td></td></tr>

<script type="text/javascript">
$(function() {
    $('#data_0__public_0, #data_0__public_1').on('change', function() {
        if ( $('#data_0__public_0:checked, #data_0__public_1:checked').val() == 1) {
            $('#teams').hide();
        } else {
            $('#teams').show();
        }
    });
});
</script>

</table>

<h3>Problems</h3>
<?php echo addHidden("data[0][mapping][0][items]", ''); ?>
<?php

$current_problems = $DB->q("TABLE SELECT contestproblem.*, problem.name FROM contestproblem
                            INNER JOIN problem USING (probid)
                            WHERE cid = %i ORDER BY shortname", $id);

foreach ($current_problems as &$current_problem) {
    $current_problem['allow_submit'] = (int)$current_problem['allow_submit'];
    $current_problem['allow_judge'] = (int)$current_problem['allow_judge'];
    $current_problem['color'] = (string)$current_problem['color'];
}
unset($current_problem);

$prepopulate = $DB->q("TABLE SELECT problem.probid AS id, problem.name, contestproblem.points,
                       CONCAT(problem.name, ' (p', problem.probid, ')') AS search
                       FROM problem INNER JOIN contestproblem USING (probid)
                       WHERE cid = %i ORDER BY shortname", $id);

$problem_name_mapping = $DB->q("KEYVALUETABLE SELECT probid, name FROM problem");
?>

<input type="text" id="problems_token_input" name="problems" />
<script type="text/javascript">
$(function() {
    $('#problems_token_input').tokenInput('ajax_problems.php', {
        overwriteClasses: {
            tokenList: 'token-input-list token-input-list-wide'
        },
        propertyToSearch: 'search',
        hintText: 'Type to search for problem ID or name',
        noResultsText: 'No problems found',
        preventDuplicates: true,
        excludeCurrent: true,
        prePopulate: <?php echo json_encode($prepopulate); ?>,
        onAdd: function(item) {
            addRow(item.id);
        },
        onDelete: function(item) {
            deleteRow(item.id);
        }
    });

    var current_problems = <?php echo json_encode($current_problems); ?>;
    var problem_name_mapping = <?php echo json_encode($problem_name_mapping); ?>;

    $.each(current_problems, function(i, problem) {
        addRow(problem.probid);
    });

    function addRow(probId) {
        var $template = $('#contestproblem_template');
        var $table = $('#problems_table');
        var maxId = $table.data('max-id');
        if ( maxId === undefined ) {
            // If not set on the table yet, we start at 0
            maxId = 0;
        } else {
            // Oterwise we should add 1 to the old value
            maxId++;
        }

        // Set it back on the table
        $table.data('max-id', maxId);

        var contest_problem_data = {
            shortname: '',
            points: 1,
            allow_submit: true,
            allow_judge: true,
            color: '',
            lazy_eval_results: ''
        };

        for ( var i = 0; i < current_problems.length; i++ ) {
            if ( current_problems[i].probid == probId ) {
                contest_problem_data = current_problems[i];
                break;
            }
        }

        var templateContents = $template.text()
            .replace(/\{id\}/g, maxId)
            .replace(/\{probid\}/g, probId)
            .replace(/\{name\}/g, problem_name_mapping[probId])
            .replace(/\{shortname\}/g, contest_problem_data.shortname)
            .replace(/\{points\}/g, contest_problem_data.points)
            .replace(/\{color\}/g, contest_problem_data.color)
            .replace(/\{lazy_eval_results\}/g, contest_problem_data.lazy_eval_results);

        $('tbody', $table).append(templateContents);

        // Set allow submit / allow judge
        var submit_id = '#data_0__mapping__0__extra__' + maxId + '__allow_submit_';
        if ( contest_problem_data.allow_submit ) {
            submit_id += '1';
        } else {
            submit_id += '0';
        }
        $(submit_id).attr('checked', 'checked');

        var judge_id = '#data_0__mapping__0__extra__' + maxId + '__allow_judge_';
        if ( contest_problem_data.allow_judge ) {
            judge_id += '1';
        } else {
            judge_id += '0';
        }
        $(judge_id).attr('checked', 'checked');

        jscolor.bind();
    }

    function deleteRow(probId) {
        var $tr = $('#problems_table tr[data-problem=' + probId + ']');
        $tr.remove();
    }
});
</script>
<br />
<script type="text/template" id="contestproblem_template">
<tr data-problem="{probid}">
    <td>
        <?php echo addHidden("data[0][mapping][0][items][{id}]", '{probid}'); ?>
        p{probid}
    </td>
    <td>
        {name}
    </td>
    <td>
        <?php echo addInput("data[0][mapping][0][extra][{id}][shortname]", '{shortname}', 8, 10, 'required'); ?>
    </td>
    <td>
        <?php echo addInputField(
    'number',
    "data[0][mapping][0][extra][{id}][points]",
                                 '{points}',
    ' style="width:10ex" min="0" max="9999" required'
); ?>
    </td>
    <td>
        <?php echo addRadioButton("data[0][mapping][0][extra][{id}][allow_submit]", true, 1); ?>
        <label for='data_0__mapping__0__extra__{id}__allow_submit_1'>yes</label>
        <?php echo addRadioButton("data[0][mapping][0][extra][{id}][allow_submit]", false, 0); ?>
        <label for='data_0__mapping__0__extra__{id}__allow_submit_0'>no</label>
    </td>
    <td>
        <?php echo addRadioButton("data[0][mapping][0][extra][{id}][allow_judge]", true, 1); ?>
        <label for='data_0__mapping__0__extra__{id}__allow_judge_1'>yes</label>
        <?php echo addRadioButton("data[0][mapping][0][extra][{id}][allow_judge]", false, 0); ?>
        <label for='data_0__mapping__0__extra__{id}__allow_judge_0'>no</label>
    </td>
    <td>
        <?php echo addInput(
                                     "data[0][mapping][0][extra][{id}][color]",
                                     '{color}',
                                     10,
                                     25,
                            'class="color {required:false,adjust:false,hash:true,caps:false}"'
                                 ); ?>
    </td>
    <td>
        <?php echo addInputField(
                                'number',
                                "data[0][mapping][0][extra][{id}][lazy_eval_results]",
                                 '{lazy_eval_results}',
                                ' style="width:10ex" min="0" max="1"'
                            ); ?>
    </td>
</tr>
</script>
<table id="problems_table">
    <thead>
    <tr>
        <th>ID</th>
        <th>name</th>
        <th>short name</th>
            <th>points</th>
        <th>allow submit</th>
        <th>allow judge</th>
        <th>colour
        <a target="_blank" href="https://en.wikipedia.org/wiki/Web_colors">
        <img src="../images/b_help.png" class="smallpicto" alt="?"></a></th>
        <th>lazy eval</th>
    </tr>
    </thead>
    <tbody>
        <!-- Will be filled in javascript -->
    </tbody>
</table>

<script type="text/javascript">
function clearTeamsOnPublic() {
    if ( $('#data_0__public_0:checked, #data_0__public_1:checked').val() == 1) {
        $('#data_0__mapping__1__items_').val('');
    }
}
</script>

<?php
echo addHidden('data[0][mapping][0][fk][0]', 'cid') .
     addHidden('data[0][mapping][0][fk][1]', 'probid') .
     addHidden('data[0][mapping][0][table]', 'contestproblem');
echo addHidden('data[0][mapping][1][fk][0]', 'cid') .
     addHidden('data[0][mapping][1][fk][1]', 'teamid') .
     addHidden('data[0][mapping][1][table]', 'contestteam');
echo addHidden('cmd', $cmd) .
    addHidden('table', 'contest') .
    addHidden('referrer', @$_GET['referrer'] . ($cmd == 'edit'?(strstr(@$_GET['referrer'], '?') === false?'?edited=1':'&edited=1'):'')) .
    addSubmit('Save', null, 'clearTeamsOnPublic()') .
    addSubmit('Cancel', 'cancel', null, true, 'formnovalidate' . (isset($_GET['referrer']) ? ' formaction="' . specialchars($_GET['referrer']) . '"':'')) .
    addEndForm();

require(LIBWWWDIR . '/footer.php');
exit;

endif;

if (! $id) {
    error("Missing or invalid contest id");
}

if (isset($_GET['edited'])) {
    echo addForm('refresh_cache.php') .
            msgbox(
                "Warning: Refresh scoreboard cache",
        "If the contest start time was changed, it may be necessary to recalculate any cached scoreboards.<br /><br />" .
        addSubmit('recalculate caches now', 'refresh')
        ) .
        addHidden('cid', $id) .
        addEndForm();
}


$data = $DB->q('TUPLE SELECT * FROM contest WHERE cid = %i', $id);

echo "<h1>Contest: ".specialchars($data['name'])."</h1>\n\n";

if (in_array($data['cid'], $cids)) {
    echo "<p><em>This is an active contest.</em></p>\n\n";
}
if (!$data['enabled']) {
    echo "<p><em>This contest is disabled.</em></p>\n\n";
}
if (!empty($data['finalizetime'])) {
    echo "<p><em>This contest is final.</em></p>\n\n";
}

$teams = $DB->q("TABLE SELECT team.*
                 FROM team INNER JOIN contestteam USING (teamid)
                 WHERE cid = %i", $id);
$numprobs = $DB->q("VALUE SELECT COUNT(*) AS problemcount
                    FROM contestproblem
                    WHERE cid = %i", $id);


echo "<table>\n";
echo '<tr><td>CID:</td><td>c' .
    (int)$data['cid'] . "</td></tr>\n";
echo '<tr><td>Short name:</td><td>' .
     specialchars($data['shortname']) .
     "</td></tr>\n";
echo '<tr><td>Name:</td><td>' .
    specialchars($data['name']) .
    "</td></tr>\n";
echo '<tr><td>Activate time:</td><td>' .
    specialchars(@$data['activatetime_string']) .
    "</td></tr>\n";
echo '<tr><td>Start time:</td><td>' .
    ($data['starttime_enabled'] ? '' : '<span class="ignore">') .
    specialchars($data['starttime_string']) .
    ($data['starttime_enabled'] ? '' : '</span> <em>delayed</em>') .
    "</td></tr>\n";
echo '<tr><td>Scoreboard freeze:</td><td>' .
    (empty($data['freezetime_string']) ? "-" : specialchars(@$data['freezetime_string'])) .
    "</td></tr>\n";
echo '<tr><td>End time:</td><td>' .
    specialchars($data['endtime_string']) .
    "</td></tr>\n";;
echo '<tr><td>Scoreboard unfreeze:</td><td>' .
    (empty($data['unfreezetime_string']) ? "-" : specialchars(@$data['unfreezetime_string'])) .
    "</td></tr>\n";
echo '<tr><td>Dectivate time:</td><td>' .
     specialchars(@$data['deactivatetime_string']) .
     "</td></tr>\n";
echo '<tr><td>Process balloons:</td><td>' .
     ($data['process_balloons'] ? 'yes' : 'no') .
     "</td></tr>\n";
echo '<tr><td>Public:</td><td>' .
     ($data['public'] ? 'yes' : 'no') .
     "</td></tr>\n";
echo '<tr><td>Teams:</td><td>';
if ($data['public']) {
    echo "<em>all teams</em>";
} else {
    if (empty($teams)) {
        echo '<em>no teams</em>';
    } else {
        foreach ($teams as $i => $team) {
            if ($i != 0) {
                echo '</td></tr>';
                echo '<tr><td></td><td>';
            }
            echo '<a href="team.php?id=' . $team['teamid'] . '&cid=' . $id . '">';
            echo $team['name'] . ' (t' . $team['teamid'] . ')';
            echo '</a>';
        }
    }
}
echo '</td></tr>';
echo "</table>\n\n";

if (IS_ADMIN) {
    echo "<p>" .
        editLink('contest', $data['cid']) . "\n" .
        delLink('contest', 'cid', $data['cid'], $data['name']) ."</p>\n\n";

    if (in_array($data['cid'], $cids)) {
        echo rejudgeForm('contest', $data['cid']) . "<br />\n\n";
    }
}


if (!empty($data['finalizetime'])) {
    echo "<h3>Finalized</h3>\n\n";
    echo "<table>\n" .
        "<tr><td>Finalized at:</td><td>" . printtime($data['finalizetime'], '%Y-%m-%d %H:%M:%S (%Z)') . "</td></tr>\n" .
         "<tr><td>B:</td><td>" . htmlspecialchars($data['b']) . "</td></tr>\n" .
        "</table>\n<p>Comment:</p>\n<pre class=\"output_text\">" . htmlspecialchars($data['finalizecomment']) . "</pre>\n";

    echo "<p><a href=\"finalize.php?id=" . (int)$data['cid'] . "\">update finalization</a></p>\n\n";
} else {
    echo "<p><a href=\"finalize.php?id=" . (int)$data['cid'] . "\">finalize this contest</a></p>\n\n";
}

if (ALLOW_REMOVED_INTERVALS) {
    echo "<h3>Removed intervals</h3>\n\n";

    $removals = $DB->q('TABLE SELECT * FROM removed_interval
                        WHERE cid = %i ORDER BY starttime', $id);

    if (count($removals)==0 && ! IS_ADMIN) {
        echo "<p class=\"nodata\">None.</p>\n\n";
    } else {
        if (IS_ADMIN) {
            echo addForm('removed_intervals.php') .
                addHidden('cmd', 'add') . addHidden('cid', $id);
        }
        echo "<table class=\"list\">\n<thead><tr>" .
             "<th>id</th><th>from</th><th></th><th>to</th><th>duration</th><th></th>" .
             "</tr></thead>\n<tbody>\n";
        $iseven = false;
        foreach ($removals as $row) {
            echo '<tr class="' . ($iseven ? 'roweven' : 'rowodd') . '">' .
                 "<td>" . $row['intervalid'] . "</td>" .
                 "<td>" . $row['starttime_string'] . "</td><td>&nbsp;&rarr;&nbsp;</td>" .
                 "<td>" . $row['endtime_string'] . "</td><td class=\"tdright\">&nbsp;" .
                 printtimediff($row['starttime'], $row['endtime']) . "</td>" .
                 "<td><a href=\"removed_intervals.php?cmd=delete&amp;cid=$id&amp;intervalid=" .
                 (int)$row['intervalid'] . "\" onclick=\"return confirm('Really delete interval?');\">" .
                 "<img src=\"../images/delete.png\" alt=\"delete\" class=\"picto\" /></a></td>" .
                 "</tr>\n";
            $iseven = !$iseven;
        }
        if (IS_ADMIN) {
            echo "<tr><td>new&nbsp;</td>" .
                 "<td>" . addInput('starttime_string', null, 30, 64,
                                   'required pattern="' . $pattern_datetime . '"') .
                 "</td><td>&nbsp;&rarr;&nbsp;</td>" .
                 "<td>" . addInput('endtime_string', null, 30, 64,
                                   'required pattern="' . $pattern_datetime . '"') .
                 "</td><td></td><td>" . addSubmit('Add') . "</td></tr>\n";
        }
        echo "</tbody>\n</table>\n" .
             "<p>Use the format <b><kbd>$human_abs_datetime</kbd></b> " .
             "for start/end times.</p>\n";
        if (IS_ADMIN) {
            echo addEndForm();
        }
    }
}

echo "<h3>Problems</h3>\n\n";

$res = $DB->q('TABLE SELECT *
               FROM problem
               INNER JOIN contestproblem USING (probid)
               WHERE cid = %i
               ORDER BY shortname', $id);

if (count($res) == 0) {
    echo "<p class=\"nodata\">No problems added yet</p>\n\n";
} else {
    echo "<table class=\"list sortable\">\n<thead>\n" .
         "<tr><th scope=\"col\" class=\"sorttable_numeric\">probid</th>";
    echo "<th scope=\"col\">name</th>";
    echo "<th scope=\"col\">shortname</th>";
    echo "<th scope=\"col\">points</th>";
    echo "<th scope=\"col\">allow<br />submit</th>";
    echo "<th scope=\"col\">allow<br />judge</th>";
    echo "<th class=\"sorttable_nosort\" scope=\"col\">colour</th>\n";
    echo "<th scope=\"col\">lazy eval</th>\n";
    echo "<th scope=\"col\"></th>\n";
    echo "</tr>\n</thead>\n<tbody>\n";

    $iseven = false;
    foreach ($res as $row) {
        $link = '<a href="problem.php?id=' . urlencode($row['probid']) . '">';

        echo '<tr class="' .
             ($iseven ? 'roweven' : 'rowodd') . '">' .
             "<td class=\"tdright\">" . $link .
             "p" . (int)$row['probid'] . "</a></td>\n";
        echo "<td>" . $link . specialchars($row['name']) . "</a></td>\n";
        echo "<td>" . $link . specialchars($row['shortname']) . "</a></td>\n";
        echo "<td>" . $link . specialchars($row['points']) . "</a></td>\n";
        echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_submit']) . "</a></td>\n";
        echo "<td class=\"tdcenter\">" . $link . printyn($row['allow_judge']) . "</a></td>\n";
        echo(!empty($row['color'])
            ? '<td title="' . specialchars($row['color']) .
              '">' . $link . '<div class="circle" style="background-color: ' .
              specialchars($row['color']) .
              ';"></div></a></td>'
            : '<td>'. $link . '&nbsp;</a></td>');
        echo "<td>" . $link . (isset($row['lazy_eval_results']) ?
                                printyn($row['lazy_eval_results']) : '-') . "</a></td>\n";
        if (IS_ADMIN) {
            echo "<td>" .
                 delLinkMultiple(
                     'contestproblem',
                     array('cid','probid'),
                     array($id, $row['probid']),
                     'contest.php?id='.$id,
                     $row['shortname']
                 ) ."</td>";
        }

        $iseven = !$iseven;

        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
