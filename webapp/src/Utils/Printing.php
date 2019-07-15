<?php declare(strict_types=1);
namespace App\Utils;

/**
 * Functionality for making printouts from DOMjudge.
 */
class Printing
{
    /**
     * Function to send a local file to the printer.
     * Change this to match your local setup.
     *
     * The following parameters are available. Make sure you escape
     * them correctly before passing them to the shell.
     *   $filename: the on-disk file to be printed out
     *   $origname: the original filename as submitted by the team
     *   $language: langid of the programming language this file is in
     *   $username: username of the print job submitter
     *   $teamname: teamname of the team this user belongs to
     *   $location: room/place to bring it to.
     *
     * Returns array with two elements: first a boolean indicating
     * overall success, and second a string to be displayed to the user.
     *
     * The default configuration of this function depends on the enscript
     * tool. It will optionally format the incoming text for the
     * specified language, and adds a header line with the team ID for
     * easy identification. To prevent misuse the amount of pages per
     * job is limited to 10.
     */
    public static function send(string $filename, string $origname,
        $language = null, string $username, string $teamname, $location = null) : array
    {
        // Map our language to enscript language:
        $lang_remap = array(
            'adb'    => 'ada',
            'bash'   => 'sh',
            'csharp' => 'c',
            'f95'    => 'f90',
            'hs'     => 'haskell',
            'js'     => 'javascript',
            'pas'    => 'pascal',
            'pl'     => 'perl',
            'py'     => 'python',
            'py2'    => 'python',
            'py3'    => 'python',
            'rb'     => 'ruby',
        );
        if (isset($language) && array_key_exists($language, $lang_remap)) {
            $language = $lang_remap[$language];
        }
        $highlight = "";
        if (! empty($language)) {
            $highlight = "-E" . escapeshellarg($language);
        }
    
        $header = sprintf("Team: %s %s ", $username, $teamname) .
                  (!empty($location) ? "[".$location."]":"") .
                  " File: $origname||Page $% of $=";
    
        // For debugging or spooling to a different host.
        // Also uncomment '-p $tmp' below.
        //$tmp = tempnam('/tmp', 'print_'.$username.'_');
    
        $cmd = "enscript -C " . $highlight
             . " -b " . escapeshellarg($header)
             . " -a 0-10 "
             . " -f Courier9 "
             //. " -p $tmp "
             . escapeshellarg($filename) . " 2>&1";
    
        exec($cmd, $output, $retval);
    
        // Make file readable for others than webserver user,
        // and give it an extension:
        //chmod($tmp, 0644);
        //rename($tmp, $tmp.'.ps');
    
        return [$retval == 0, implode("\n", $output)];
    }
}
