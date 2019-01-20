<?php declare(strict_types=1);
/**
 * Clarification helper functions for jury and teams
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require_once(LIBDIR . '/lib.misc.php');

/**
 * Marks a given clarification as viewed by a specific team,
 * so it doesn't show up as "unread" anymore in their interface.
 */
function setClarificationViewed(int $clar, int $team)
{
    global $DB;
    $DB->q('DELETE FROM team_unread WHERE mesgid = %i AND teamid = %i', $clar, $team);
}

/**
 * Returns wether a team is allowed to view a clarification.
 */
function canViewClarification(int $team, array $clar) : bool
{
    return (
           $clar['sender'] == $team
        || $clar['recipient'] == $team
        || ($clar['sender'] == null && $clar['recipient'] == null)
        );
}

/**
 * Returns the list of clarification categories as a key,value array.
 * Keys should be non-numeric to distinguish them from problem IDs.
 */
function getClarCategories() : array
{
    $categs = dbconfig_get('clar_categories');

    $clarcategories = array();
    foreach ($categs as $key => $val) {
        $clarcategories[$key] = $val;
    }

    return $clarcategories;
}

/**
 * Returns the list of clarification queues as a key,value array.
 */
function getClarQueues() : array
{
    $queues = dbconfig_get('clar_queues');

    $clarqueues = [null => 'Unassigned issues'];
    foreach ($queues as $key => $val) {
        $clarqueues[$key] = $val;
    }

    return $clarqueues;
}

/**
 * Output a single clarification.
 * Helperfunction for putClarification, do _not_ use directly!
 */
function putClar(array $clar)
{
    global $cids;

    // $clar['sender'] is set to the team ID, or empty if sent by the jury.
    if (!empty($clar['sender'])) {
        $from = specialchars($clar['fromname'] . ' (t'.$clar['sender'] . ')') ;
    } else {
        $from = 'Jury';
    }
    if ($clar['recipient'] && empty($clar['sender'])) {
        $to = specialchars($clar['toname'] . ' (t'.$clar['recipient'] . ')') ;
    } else {
        $to = ($clar['sender']) ? 'Jury' : 'All';
    }

    echo "<table>\n";

    echo '<tr><td>From:</td><td>';
    echo $from;
    echo "</td></tr>\n";

    echo '<tr><td>To:</td><td>';
    echo $to;
    echo "</td></tr>\n";

    $categs = getClarCategories();

    echo '<tr><td>Subject:</td><td>';
    $prefix = '';
    $currentSelectedCategory = null;
    if (is_null($clar['probid'])) {
        if (is_null($clar['category'])) {
            // FIXME: why does it make sense to keep clars for a dropped problem and relabel them to general issue?
            echo $prefix . "General issue";
        } else {
            if (array_key_exists($clar['category'], $categs)) {
                echo $prefix . specialchars($categs[$clar['category']]);
                $currentSelectedCategory = $clar['cid'] . '-' . $clar['category'];
            } else {
                echo $prefix . "General issue";
            }
        }
    } else {
            echo 'Problem ' . specialchars($clar['shortname'] . ": " . $clar['probname']);
    }
    echo "</td></tr>\n";

    echo '<tr><td>Time:</td><td>';
    echo printtime($clar['submittime'], null, $clar['cid']);
    echo "</td></tr>\n";

    echo '<tr><td></td><td class="filename w-100">';
    echo '<pre class="output_text w-auto">' . specialchars(wrap_unquoted($clar['body'], 80)) . "</pre>";
    echo "</td></tr>\n";

    echo "</table>\n";

    return;
}

/**
 * Output a clarification (and thread) for id $id.
 */
function putClarification(int $id, $team = null)
{
    if ($team==null && ! IS_JURY) {
        error("access denied to clarifications: you seem to be team nor jury");
    }

    global $DB, $cids;

    $clar = $DB->q('TUPLE SELECT * FROM clarification WHERE clarid = %i', $id);

    $clars = $DB->q('SELECT c.*, cp.shortname, p.name AS probname,
                     t.name AS toname, f.name AS fromname,
                     co.shortname AS contestshortname
                     FROM clarification c
                     LEFT JOIN problem p ON (c.probid = p.probid)
                     LEFT JOIN team t ON (t.teamid = c.recipient)
                     LEFT JOIN team f ON (f.teamid = c.sender)
                     LEFT JOIN contest co ON (co.cid = c.cid)
                     LEFT JOIN contestproblem cp ON (cp.probid = c.probid AND
                                                     cp.cid = c.cid AND
                                                     cp.allow_submit = 1)
                     WHERE c.respid = %i OR c.clarid = %i
                     ORDER BY c.submittime, c.clarid',
                    $clar['clarid'], $clar['clarid']);

    while ($clar = $clars->next()) {
        // check permission to view this clarification
        if (IS_JURY || canViewClarification($team, $clar)) {
            if ($team !== null) {
                setClarificationViewed((int)$clar['clarid'], $team);
            }
            putClar($clar);
            echo "<br />\n\n";
        }
    }
}

/**
 * Summarize a clarification.
 * Helper function for putClarificationList.
 */
function summarizeClarification(string $body) : string
{
    // when making a summary, try to ignore the quoted text, and
    // replace newlines by spaces.
    $split = explode("\n", $body);
    $newbody = '';
    foreach ($split as $line) {
        if (strlen($line) > 0 && $line{0} != '>') {
            $newbody .= $line . ' ';
        }
    }
    return specialchars(str_cut((empty($newbody) ? $body : $newbody), 80));
}

/**
 * Print a list of clarifications in a table with links to the clarifications.
 */
function putClarificationList($clars, int $team = null)
{
    global $username, $cids;

    if ($team==null && ! IS_JURY) {
        error("access denied to clarifications: you seem to be team nor jury");
    }

    $categs = getClarCategories();

    echo "<table class=\"table table-striped table-hover table-sm list sortable\">\n<thead class=\"thead-light\">\n<tr>" .
         "<th scope=\"col\">time</th>" .
         "<th scope=\"col\">from</th>" .
         "<th scope=\"col\">to</th><th scope=\"col\">subject</th>" .
         "<th scope=\"col\">text</th>" .
         "</tr>\n</thead>\n<tbody>\n";

    while ($clar = $clars->next()) {
        // check viewing permission for teams
        if (! IS_JURY && !canViewClarification($team, $clar)) {
            continue;
        }

        $clar['clarid'] = (int)$clar['clarid'];
        $link = '<a href="clarification.php?id=' . urlencode((string)$clar['clarid'])  . '">';

        if (isset($clar['unread'])) {
            echo '<tr class="unread">';
        } else {
            echo '<tr>';
        }

        echo '<td>' . $link . printtime($clar['submittime'], null, $clar['cid']) . '</a></td>';

        if ($clar['sender'] == null) {
            $sender = 'Jury';
            if ($clar['recipient'] == null) {
                $recipient = 'All';
            } else {
                if ($team != null && $clar['recipient'] == $team) {
                    $recipient = 'You';
                } else {
                    $recipient = specialchars($clar['toname']);
                }
            }
        } else {
            if ($team != null && $clar['sender'] == $team) {
                $sender = 'You';
            } else {
                $sender = specialchars($clar['fromname']);
            }
            $recipient = 'Jury';
        }

        echo '<td>' . $link . $sender . '</a></td>' .
             '<td>' . $link . $recipient . '</a></td>';

        echo '<td>' . $link;
        if (is_null($clar['probid'])) {
            if (is_null($clar['category'])) {
                // FIXME: why does it make sense to keep clars for a dropped problem and relabel them to general issue?
                echo "general";
            } else {
                if (array_key_exists($clar['category'], $categs)) {
                    echo specialchars($categs[$clar['category']]);
                } else {
                    echo "general";
                }
            }
        } else {
            echo "problem ".$clar['shortname'];
        }
        echo "</a></td>";

        echo '<td class="clartext">' . $link .
            summarizeClarification($clar['body']) . "</a></td>";

        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n\n";
}

/**
 * Output a form to send a new clarification.
 * Set respid to a clarid, to make only responses to same
 * sender(s)/recipient(s) or ALL selectable.
 */
function putClarificationForm(string $action, $respid = null, $onlycontest = null, $teamto = null)
{
    global $cdata, $teamdata, $DB;

    $cdatas = getCurContests(true, IS_JURY ? null : $teamdata['teamid']);
    if (isset($onlycontest)) {
        $cdatas = array($onlycontest => $cdatas[$onlycontest]);
    }
    $cids = array_keys($cdatas);
    if (empty($cids)) {
        echo '<p class="nodata">No active contests</p>';
        return;
    }

    // get clarification this form is responding to
    if ($respid) {
        $clar = $DB->q('MAYBETUPLE SELECT c.*, t.name AS toname, f.name AS fromname
                        FROM clarification c
                        LEFT JOIN team t ON (t.teamid = c.recipient)
                        LEFT JOIN team f ON (f.teamid = c.sender)
                        WHERE c.clarid = %i', $respid);
    }

    // Select box for a specific problem (only when the contest
    // has started) or other issues.
    $categs = getClarCategories();
    $defclar = key($categs);
    $subject_options = array();
    foreach ($cdatas as $cid => $data) {
        foreach ($categs as $categid => $categname) {
                $subject_options["$cid-$categid"] = $categname;
        }
        $fdata = calcFreezeData($data);
        if ($fdata['started']) {
            $problem_options =
                $DB->q('KEYVALUETABLE SELECT CONCAT(cid, "-", probid),
                                             CONCAT(shortname, ": ", name) as name
                        FROM problem
                        INNER JOIN contestproblem USING (probid)
                        WHERE cid = %i AND allow_submit = 1
                        ORDER BY shortname ASC', $cid);
            $subject_options += $problem_options;
        }
    }

    if ($respid) {
        if (is_null($clar['probid'])) {
            $subject_selected = $clar['cid'] . '-' . $clar['category'];
        } else {
            $subject_selected = $clar['cid'] . '-' . $clar['probid'];
        }
    } else {
        $subject_selected = null;
        if (!empty($cdata)) {
            $subject_selected = $cdata['cid'] . '-' . $defclar;
        }
    }

    $body = "";
    if ($respid) {
        $text = explode("\n", wrap_unquoted($clar['body']), 75);
        foreach ($text as $line) {
            $body .= "> $line\n";
        }
    } ?>

<script>
function confirmClar() {
    return confirm("Send clarification request to Jury?");
}
</script>

<div class="container clarificationform">
<form action="<?=specialchars($action)?>" method="post" id="sendclar" onsubmit="return confirmClar();">

<div class="form-group">
<label for="sendto">Send to:</label>
<?php 
        echo "<select id=\"sendto\" class=\"custom-select disabled\" disabled>\n<option>Jury</option>\n</select>\n";
     ?>
</div>

<div class="form-group">
<label for="subject">Subject:</label>
<select name="problem" id="subject" class="custom-select">
<?php
foreach ($subject_options as $value => $desc) {
        echo "<option value=\"" . specialchars($value) . "\"" .
        (($value === $subject_selected) ? ' selected': '') .
               ">" . specialchars($desc) . "</option>\n";
    } ?>
</select>
</div>

<div class="form-group">
<label for="bodytext">Message:</label>
<textarea class="form-control" name="bodytext" id="bodytext" rows="5" cols="85" required><?=specialchars($body); ?></textarea>
</div>

<div class="form-group">
<button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-envelope"></i> Send</button>
</div>
</form>
</div>

<?php
}
