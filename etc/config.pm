# Configuration file for perl scripts

# Include in scripts with 'BEGIN { require "<filename>"; }'

# Globally require perl version
require 5.002;

# TCP port on which submitdaemon listens
our $SUBMITPORT = 9147;

# Root-paths for different parts of the system
our $SYSTEM_ROOT = "/home/cies/nkp0405/systeem/svn/jury";
our $OUTPUT_ROOT = "/home/cies/nkp0405/systeem/systest";
our $INPUT_ROOT  = "/home/cies/nkp0405/opgaven";

# Paths within OUTPUT_ROOT
our $INCOMINGDIR = $OUTPUT_ROOT . "/incoming";
our $SUBMITDIR   = $OUTPUT_ROOT . "/sources";
our $JUDGEDIR    = $OUTPUT_ROOT . "/judging";

# Directory where submit-client puts files for sending (relative to $HOME)
our $USERSUBMITDIR = ".submit";

# For extra clarity in return statements (perl specific)
our $success = 1;
our $failure = 0;

# End of configuration file: end with true (needed by 'require')
1;
