<?php
/**
 * View or add/edit a row in a judgehost_restriction
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
require(LIBWWWDIR . '/scoreboard.php');

$id = getRequestID();
$title = ucfirst((empty($_GET['cmd']) ? '' : specialchars($_GET['cmd']) . ' ') .
         'judgehost restriction');

$jqtokeninput = true;

// Get these lists here, as they are needed both on the edit and view pages.
$contests  = $DB->q("KEYVALUETABLE SELECT cid, CONCAT(name, ' (', shortname, ' - c', cid, ')')
                     FROM contest ORDER BY cid");
$problems  = $DB->q("KEYVALUETABLE SELECT probid, CONCAT(name, ' (p', probid, ')')
                     FROM problem ORDER BY probid");
$languages = $DB->q("KEYVALUETABLE SELECT langid, CONCAT(name, ' (', langid, ')')
                     FROM language ORDER BY langid");
$lists = array('contest' => $contests,
               'problem' => $problems,
               'language' => $languages);

require(LIBWWWDIR . '/header.php');

if (!empty($_GET['cmd'])) {
    requireAdmin();

    $cmd = $_GET['cmd'];

    echo "<h2>$title</h2>\n\n";

    echo addForm('judgehost_restrictions.php');

    echo "<table>\n";

    if ($cmd == 'edit') {
        $row = $DB->q('MAYBETUPLE SELECT * FROM judgehost_restriction
                       WHERE restrictionid = %i', $id);
        if (!$row) {
            error("Missing or invalid judgehost restriction id");
        }

        $row['restrictions'] = dj_json_decode($row['restrictions']);

        echo "<tr><td>ID:</td><td>" .
             addHidden('keydata[0][restrictionid]', $row['restrictionid']) .
             specialchars($row['restrictionid']) . "</td></tr>\n";
    } ?>
<tr><td><label for="data_0__name_">Name:</label></td>
    <td><?php echo addInput('data[0][name]', @$row['name'], 15, 255, 'required')?></td></tr>
<?php

$types = array(
    'contest' => array(
        'ajax' => 'ajax_contests.php',
        'hintText' => 'Type to search for contest ID, name, or short name',
        'noResultsText' => 'No contests found',
        'allData' => $contests,
    ),
    'problem' => array(
        'ajax' => 'ajax_problems.php',
        'hintText' => 'Type to search for problem ID or name',
        'noResultsText' => 'No problems found',
        'allData' => $problems,
    ),
    'language' => array(
        'ajax' => 'ajax_languages.php',
        'hintText' => 'Type to search for language ID or name',
        'noResultsText' => 'No languages found',
        'allData' => $languages,
    ),
);
    foreach ($types as $type => $type_settings) {
        ?>
<tr><td colspan="2">
<h3>Restrict to any of the following <?php echo $type; ?>s (leave empty to allow all)</h3>
</td></tr>
<?php
$prepopulate = array();
        if (is_array($row['restrictions'][$type])) {
            foreach ($row['restrictions'][$type] as $id) {
                $prepopulate[] = array(
            'id' => $id,
            'search' => $type_settings['allData'][$id],
        );
            }
        } ?>
<tr><td></td><td><?php echo addInput("data[0][restrictions][$type]", '', 50); ?></td></tr>
<script type="text/javascript">
    $(function() {
        $('#data_0__restrictions__<?php echo $type; ?>_').tokenInput('<?php echo $type_settings['ajax']; ?>', {
            propertyToSearch: 'search',
            hintText: '<?php echo $type_settings['hintText']; ?>',
            noResultsText: '<?php echo $type_settings['noResultsText']; ?>',
            preventDuplicates: true,
            excludeCurrent: true,
            prePopulate: <?php echo json_encode($prepopulate); ?>
        });
    });
</script>
<?php
    }

    $rejudge_own = !isset($row['restrictions']['rejudge_own']) ||
      (bool)$row['restrictions']['rejudge_own'];

    echo '<tr><td>Allow rejudge on same judgehost:</td><td>' .
        addRadioButton('data[0][restrictions][rejudge_own]', $rejudge_own, 1) .
        '<label for="data_0__restrictions__rejudge_own_1">yes</label>' .
        addRadioButton('data[0][restrictions][rejudge_own]', !$rejudge_own, 0) .
        '<label for="data_0__restrictions__rejudge_own_0">no</label>' .
        "</td></tr>\n";

    echo "</table>\n\n";

    echo addHidden('cmd', $cmd) .
         addHidden('table', 'judgehost_restriction') .
         addHidden('referrer', @$_GET['referrer']) .
         addSubmit('Save') .
         addSubmit('Cancel', 'cancel', null, true, 'formnovalidate') .
         addEndForm();

    require(LIBWWWDIR . '/footer.php');
    exit;
}

$data = $DB->q('TUPLE SELECT * FROM judgehost_restriction WHERE restrictionid = %i', $id);
if (!$data) {
    error("Missing or invalid restriction id");
}

echo "<h1>Restriction: " . specialchars($data['name']) . "</h1>\n\n";

echo "<table>\n";
echo '<tr><td>ID:</td><td>' . specialchars($data['restrictionid']) . "</td></tr>\n";
echo '<tr><td>Name:</td><td>' . specialchars($data['name']) . "</td></tr>\n";

$restrictions = dj_json_decode($data['restrictions']);

foreach (array('contest','problem','language') as $type) {
    echo "<tr><td>Restrict to ${type}s:</td>";
    if (empty($restrictions[$type])) {
        echo "<td class=\"nodata\">none</td></tr>\n";
    } else {
        $first = true;
        foreach ($restrictions[$type] as $val) {
            if (!$first) {
                echo '<tr><td></td>';
            }
            $first = false;
            echo "<td>" . $lists[$type][$val] . "</td></tr>\n";
        }
    }
}

echo '<tr><td>Rejudge by same judgehost:</td><td>' .
     printyn(!isset($restrictions['rejudge_own']) ||
             (bool)$restrictions['rejudge_own']) . "</td></tr>\n";

echo "</table>\n\n";

if (IS_ADMIN) {
    echo "<p>" .
         editLink('judgehost_restriction', $data['restrictionid']) . "\n" .
         delLink(
             'judgehost_restriction',
             'restrictionid',
                 $data['restrictionid'],
             $data['name']
         ) . "</p>\n\n";
}

echo "<h2>Judgehosts having restriction " . specialchars($data['name']) . "</h2>\n\n";

$judgehosts = $DB->q('SELECT hostname, active FROM judgehost WHERE restrictionid = %i', $id);
if ($judgehosts->count() == 0) {
    echo "<p class=\"nodata\">no judgehosts</p>\n\n";
} else {
    echo "<table class=\"list\">\n<thead>\n" .
         "<tr><th scope=\"col\">hostname</th><th scope=\"col\">active</th></tr>\n" .
         "</thead>\n<tbody>\n";
    while ($judgehost = $judgehosts->next()) {
        $link = '<a href="judgehost.php?id=' . urlencode($judgehost['hostname']) . '">';
        echo "<tr".($judgehost['active'] ? '': ' class="disabled"').
             "><td>"
             . $link . specialchars($judgehost['hostname']) .
             "</a></td><td>" .
             $link . printyn($judgehost['active']) .
             "</a></td></tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

require(LIBWWWDIR . '/footer.php');
