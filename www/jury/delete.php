<?php
/**
 * Functionality to delete data from this interface.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

$t = @$_REQUEST['table'];
$referrer = @$_REQUEST['referrer'];
$desc = @$_REQUEST['desc'];
if (! preg_match('/^[._a-zA-Z0-9?&=-]*$/', $referrer)) {
    error("Invalid characters in referrer.");
}

if (!$t) {
    error("No table selected.");
}
if (!in_array($t, array_keys($KEYS))) {
    error("Unknown table.");
}

$k = array();
foreach ($KEYS[$t] as $key) {
    $k[$key] = @$_REQUEST[$key];
    if (!$k[$key]) {
        error("I can't find my keys.");
    }
}

if (isset($_POST['cancel'])) {
    if (!empty($referrer)) {
        header('Location: ' . $referrer);
    } else {
        header('Location: '.$t.'.php?id=' .
            urlencode(array_shift($k)));
    }
    exit;
}

// Send headers here, because we need to be able to redirect above this point.

$title = 'Delete from ' . $t;
require(LIBWWWDIR . '/header.php');

// Check if we can really delete this. Note that this does *not* take
// into account recursive constraints.
$warnings = array();
foreach ($k as $key => $val) {
    $tables = fk_check("$t.$key", $val);
    foreach ($tables as $table => $action) {
        switch ($action) {
        case 'RESTRICT':
            error("$t.$key \"$val\" is still referenced in $table, cannot delete.");
            // no break
        case 'CASCADE':
            $deps = fk_dependent_tables($table);
            $warn = "cascade to $table";
            if (count($deps)>0) {
                $warn .= ", and possibly to dependent tables " . implode(", ", $deps);
            }
            $warnings[] = $warn;
            break;
        case 'SETNULL':
            $warnings[] = "create dangling references in $table";
            break;
        case 'NOCONSTRAINT':
            break;
        default:
            error("$t.$key is referenced in $table with unknown action '$action'.");
        }
    }
}

if (isset($_POST['confirm'])) {

    // Deleting problem is a special case: its dependent tables do not
    // form a tree, and a delete to judging_run can only cascade from
    // judging, not from testcase. Since MySQL does not define the
    // order of cascading deletes, we need to manually first cascade
    // via submission -> judging -> judging_run.
    if ($t=='problem') {
        $DB->q('START TRANSACTION');
        $DB->q('DELETE FROM submission WHERE %SS', $k);
    }

    // LIMIT 1 is a security measure to prevent our bugs from
    // wiping a table by accident.
    $DB->q("DELETE FROM $t WHERE %SS LIMIT 1", $k);
    if ($t=='problem') {
        $DB->q('COMMIT');
    }
    auditlog($t, implode(', ', $k), 'deleted');

    // No need to do this in a transaction, since the chance of a team
    // with same ID being created at the same time is neglibible.
    if ($t==='team') {
        $DB->q('DELETE FROM scorecache WHERE %SS', $k);
        $DB->q('DELETE FROM rankcache WHERE %SS', $k);
    }

    echo "<p>" . ucfirst($t) . " <strong>" . specialchars(implode(", ", $k)) .
        "</strong> has been deleted.</p>\n\n";

    if (!empty($referrer)) {
        echo "<p><a href=\"" . $referrer .  "\">back to overview</a></p>";
    } else {
        // one table falls outside the predictable filenames
        $tablemulti = ($t == 'team_category' ? 'team_categories' : $t.'s');
        echo "<p><a href=\"" . $tablemulti . ".php\">back to $tablemulti</a></p>";
    }
} else {
    echo addForm($pagename) .
        addHidden('table', $t);
    foreach ($k as $key => $val) {
        echo addHidden($key, $val);
    }

    echo msgbox(
        "Really delete?",
        "You're about to delete $t <strong>" .
        specialchars(join(", ", array_values($k))) .
        (empty($desc) ? '' : ' "' . specialchars($desc) . '"') . "</strong>.<br />\n" .
        (count($warnings)>0 ? "<br /><strong>Warning, this will:</strong><br />" .
         implode('<br />', $warnings) : '') . "<br /><br />\n" .
        "Are you sure?<br /><br />\n\n" .
        (empty($referrer) ? '' : addHidden('referrer', $referrer)) .
        addSubmit(" Never mind... ", 'cancel') .
        addSubmit(" Yes I'm sure! ", 'confirm')
    );

    echo addEndForm();
}


require(LIBWWWDIR . '/footer.php');
