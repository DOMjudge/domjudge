<?php

/**
 * Functions for formatting certain output to the user.
 * All output is HTML-safe, so input should not be escaped.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

/**
 * prints result with correct style, '' -> judging
 */
function printresult($result, $valid = true)
{
    $start = '<span class="sol ';
    $end   = '</span>';

    switch ($result) {
        case 'too-late':
            $style = 'sol_queued';
            break;
        case '':
            $result = 'judging';
            // no break
        case 'judging':
        case 'queued':
            if (! IS_JURY) {
                $result = 'pending';
            }
            $style = 'sol_queued';
            break;
        case 'correct':
            $style = 'sol_correct';
            break;
        default:
            $style = 'sol_incorrect';
    }

    return $start . ($valid ? $style : 'disabled') . '">' . $result . $end;
}

/**
 * Print a small indicator when this judging is still being judged
 * even though the result is known, e.g. with non-lazy evaluation.
 * $judging should be an array containing (at least) the keys 'result'
 * and 'endtime' and optionally 'aborted'.
 */
function printjudgingbusy($judging)
{
    return (IS_JURY && !empty($judging['result']) &&
             empty($judging['endtime']) && !@$judging['aborted']) ?
           '&nbsp;(&hellip;)' : '';
}

/**
 * print a yes/no field, input: something that evaluates to a boolean
 */
function printyn($val)
{
    return ($val ? 'yes':'no');
}

/**
 * Print a time in default configured time_format, or formatted as
 * specified. The format is according to strftime().
 * If $cid is specified, print time relative to that contest start.
 */
function printtime($datetime, $format = null, $cid = null)
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
        return specialchars(strftime($format, floor($datetime)));
    }
}

/**
 * Formats a given hostname. If $full = true, then
 * the full hostname will be printed, else only
 * the local part (for keeping tables readable)
 */
function printhost($hostname, $full = false)
{
    // Shorten the hostname to first label, but not if it's an IP address.
    if (! $full  && !preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $hostname)) {
        $expl = explode('.', $hostname);
        $hostname = array_shift($expl);
    }

    return "<span class=\"hostname\">".specialchars($hostname)."</span>";
}

/**
 * Print the time something took from start to end (which defaults to now).
 */
function printtimediff($start, $end = null)
{
    if (is_null($end)) {
        $end = microtime(true);
    }
    $ret = '';
    $diff = floor($end - $start);

    if ($diff >= 24*60*60) {
        $d = floor($diff/(24*60*60));
        $ret .= $d . "d ";
        $diff -= $d * 24*60*60;
    }
    if ($diff >= 60*60 || isset($d)) {
        $h = floor($diff/(60*60));
        $ret .= $h . ":";
        $diff -= $h * 60*60;
    }
    $m = floor($diff/60);
    $ret .= sprintf('%02d:', $m);
    $diff -= $m * 60;
    $ret .= sprintf('%02d', $diff);

    return $ret;
}

/**
 * Print (file) size in human readable format by using B,KB,MB,GB suffixes.
 * Input is a integer (the size in bytes), output a string with suffix.
 */
function printsize($size, $decimals = 1)
{
    $factor = 1024;
    $units = array('B', 'KB', 'MB', 'GB');
    $display = (int)$size;

    $exact = true;
    for ($i = 0; $i < count($units) && $display > $factor; $i++) {
        if (($display % $factor)!=0) {
            $exact = false;
        }
        $display /= $factor;
    }

    if ($exact) {
        $decimals = 0;
    }
    return sprintf("%.${decimals}lf&nbsp;%s", round($display, $decimals), $units[$i]);
}

/**
 * Print the relative time in h:mm:ss[.uuuuuu] format.
 */
function printtimerel($rel_time, $use_microseconds = false)
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

/**
 * Cut a string at $size chars and append ..., only if neccessary.
 */
function str_cut($str, $size)
{
    // is the string already short enough?
    // we count '…' for 1 extra chars.
    if (mb_strlen($str) <= $size+1) {
        return $str;
    }

    return mb_substr($str, 0, $size) . '…';
}

/**
 * Output an html "message box"
 *
 */
function msgbox($caption, $message)
{
    return "<fieldset class=\"msgbox\"><legend>" .
        "<img src=\"../images/huh.png\" class=\"picto\" alt=\"?\" /> " .
        $caption . "</legend>\n" .
        $message .
        "</fieldset>\n\n";
}
