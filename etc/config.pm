# Configuration file for perl scripts

# Include in scripts with 'BEGIN { require "<filename>";'

# Globally require perl version
require 5.002;

# TCP port on which submitdaemon listens
our $submitport = 9147;

# Root-paths for different parts of the system
our $SYSTEM_ROOT = "/home/cies/nkp0405/systeem/svn/jury";
our $OUTPUT_ROOT = "/home/cies/nkp0405/systeem/systest";

# Directory where submit-client puts files for sending (relative to $HOME)
our $submitclientdir = ".submit";

# Directory where submitdaemon puts received files
our $submitserverdir = $OUTPUT_ROOT . "/submit";

# For extra clarity in return statements (perl specific)
our $success = 1;
our $failure = 0;

# End of configuration file: end with true (needed by 'require')
1;
