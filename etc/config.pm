# Configuration file for perl scripts
# Include in scripts with 'require "<filename>";'
package config;

# Globally require perl version
require 5.002;

# Export all variable definitions to user namespace
require Exporter;
@ISA = qw(Exporter);
@EXPORT = qw($SYSTEM_ROOT $OUTPUT_ROOT $submitport $submitclientdir $submitserverdir);

# TCP port on which submitdaemon listens
$submitport = 9147;

# Root-paths for different parts of the system
$SYSTEM_ROOT = "/home/cies/nkp0405/system/svn";
$OUTPUT_ROOT = "/home/cies/nkp0405/system/systest";

# Directory where submit-client puts files for sending (relative to $HOME)
$submitclientdir = ".submit";

# Directory where submitdaemon puts received files
$submitserverdir = $OUTPUT_ROOT . "/submit";

# End of configuration file: end with true (needed by 'require')
1;
