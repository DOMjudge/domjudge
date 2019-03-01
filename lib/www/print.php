<?php declare(strict_types=1);

/**
 * Functions for formatting certain output to the user.
 * All output is HTML-safe, so input should not be escaped.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

// TODO: still used in combined_scoreboard. Refactor them and remove this

/**
 * Print a time in default configured time_format, or formatted as
 * specified. The format is according to strftime().
 * If $cid is specified, print time relative to that contest start.
 */
function printtime($datetime, $format = null, $cid = null) : string
{
    if (empty($datetime)) {
        return '';
    }
    if (is_null($format)) {
        $format = dbconfig_get('time_format', '%H:%M');
    }
    if (isset($cid) && dbconfig_get('show_relative_time', 0)) {
        $reltime = (int)floor(calcContestTime($datetime, $cid));
        $sign = ($reltime<0 ? -1 : 1);
        $reltime *= $sign;
        // We're not showing seconds, while the last minute before
        // contest start should show as "-0:01", so if there's a
        // nonzero amount of seconds before the contest, we have to
        // add a minute.
        $s = $reltime%60;
        $reltime = ($reltime - $s)/60;
        if ($sign<0 && $s>0) {
            $reltime++;
        }
        $m = $reltime%60;
        $reltime = ($reltime - $m)/60;
        $h = $reltime;
        if ($sign<0) {
            return sprintf("-%d:%02d", $h, $m);
        } else {
            return sprintf("%d:%02d", $h, $m);
        }
    } else {
        return specialchars(strftime($format, (int)floor($datetime)));
    }
}

/**
 * Print the relative time in h:mm:ss[.uuuuuu] format.
 */
function printtimerel(float $rel_time, bool $use_microseconds = false) : string
{
    $sign = $rel_time < 0 ? '-' : '';
    $rel_time = abs($rel_time);
    $frac_str = '';

    if ($use_microseconds) {
        $frac_str = explode('.', sprintf('%.6f', $rel_time))[1];
        $rel_time = (int) floor($rel_time);
    } else {
        // For negative times we still want to floor, but we've
        // already removed the sign, so take ceil() if negative.
        $rel_time = (int) ($sign=='-' ? ceil($rel_time) : floor($rel_time));
    }

    $h = (int) floor($rel_time/3600);
    $rel_time %= 3600;

    $m = (int) floor($rel_time/60);
    $rel_time %= 60;

    $s = (int) $rel_time;

    if ($use_microseconds) {
        $s .= '.' . $frac_str;
    }

    return sprintf($sign.'%01d:%02d:%02d'.$frac_str, $h, $m, $s);
}
