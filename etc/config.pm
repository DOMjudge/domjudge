# Configuration file for perl scripts

# Include in scripts with 'BEGIN { require "<filename>"; }'

# Globally require perl version
require 5.002;

# TCP port on which submitdaemon listens
our $SUBMITPORT   = 9147;
our $SUBMITSERVER = "square.a-eskwadraat.nl";

# Root-paths for different parts of the system
our $SYSTEM_ROOT = "$ENV{HOME}/systeem/svn/jury";
our $OUTPUT_ROOT = "$ENV{HOME}/systeem/systest";
our $INPUT_ROOT  = "$ENV{HOME}/opgaven";

# Paths within OUTPUT_ROOT
our $INCOMINGDIR = $OUTPUT_ROOT . "/incoming";
our $SUBMITDIR   = $OUTPUT_ROOT . "/sources";
our $JUDGEDIR    = $OUTPUT_ROOT . "/judging";
our $LOGDIR      = $OUTPUT_ROOT . "/log";

# Directory where submit-client puts files for sending (relative to $HOME)
our $USERSUBMITDIR = ".submit";

# Loglevels
our $LOG_ERR    = 3;
our $LOG_NOTICE = 5;
our $LOG_INFO   = 6;
our $LOG_DEBUG  = 7;

# For extra clarity in return statements (perl specific)
our $success = 1;
our $failure = 0;

# End of configuration file: end with true (needed by 'require')
1;
