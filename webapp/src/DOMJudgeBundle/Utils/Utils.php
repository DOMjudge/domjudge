<?php
namespace DOMJudgeBundle\Utils;

/**
 * Generic utility class.
 */
class Utils
{
    // returns the milliseconds part of a time stamp truncated at three digits
    private static function getMillis($seconds)
    {
        return sprintf(".%03d", floor(1000*($seconds - floor($seconds))));
    }

    // prints the absolute time as yyyy-mm-ddThh:mm:ss(.uuu)?[+-]zz(:mm)?
    // (with millis if $floored is false)
    public static function absTime($epoch, $floored = false)
    {
        if ($epoch===null) {
            return null;
        }
        $millis = Utils::getMillis($epoch);
        return date("Y-m-d\TH:i:s", $epoch)
            . ($floored ? '' : $millis)
            . date("P", $epoch);
    }

    // prints a time diff as relative time as (-)?(h)*h:mm:ss(.uuu)?
    // (with millis if $floored is false)
    public static function relTime($seconds, $floored = false)
    {
        $sign = ($seconds < 0) ? '-' : '';
        $seconds = abs($seconds);
        $hours = (int)($seconds / 3600);
        $minutes = (int)(($seconds - $hours*3600)/60);
        $millis = Utils::getMillis($seconds);
        $seconds = $seconds - $hours*3600 - $minutes*60;
        return $sign . sprintf("%d:%02d:%02d", $hours, $minutes, $seconds)
            . ($floored ? '' : $millis);
    }

    /**
     * Returns epoch with microsecond resolution. Can be used to
     * simulate MySQL UNIX_TIMESTAMP() function to create insert
     * queries that do not change when replicated later.
     */
    public static function now()
    {
        return microtime(true);
    }

    /**
     * Returns >0, =0, <0 when $time1 >, =, < $time2 respectively.
     * Returned value is time difference in seconds.
     */
    public static function difftime($time1, $time2)
    {
        return $time1 - $time2;
    }
}
