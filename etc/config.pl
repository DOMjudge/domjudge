# $Id$
# Configuration file for perl scripts
#package config;

# Include in scripts with 'require "<filename>";'

# First include some globally required packages
require 5.002;
#use strict;
use Socket;
use IO;
use IO::Socket;

# Define variables with 'our' instead of 'my' to define them
# across all files

# For extra clarity in return statements
our $success = 1;
our $failure = 0;

# Variables for client-server communication
our $submitport = 9147;
our $socket;
our $lastreply;

# Paths
our $SYSTEM_ROOT = "/home/cies/nkp0405/system/svn/jury";
our $OUTPUT_ROOT = "/home/cies/nkp0405/system/systest";

# Directory where submitdaemon puts received files
our $submitserverdir = $OUTPUT_ROOT . "/submit";

# Directory where submit-client puts files for sending (relative to $HOME)
our $submitclientdir = ".submit";

# Common functions for sending/receiving data over tcp/ip
sub netchomp {
	s/\015\012//;
}

sub sendit { # 'send' is already defined
	logmsg("send: @_");
	print $socket "@_\015\012";
}

sub receive {
	if ( ! ($_ = <$socket>) ) { return $failure; }
	netchomp;
	if ( /^[^+]/ ) { error("received: $_\n"); }
	logmsg("recv: $_");
	s/^.//;
	$lastreply = $_;
	return $success;
}

# End of configuration file: end with success (needed by 'require')
$success;
