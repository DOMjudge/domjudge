#!/usr/bin/perl -w

# submit.pl - Submit program.
#
# Copyright (C) 1999 Eelco Dolstra (eelco@cs.uu.nl).
#
# $Id$
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2, or (at your option)
# any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software Foundation,
# Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.  

# Some system/site specific config
#BEGIN { require "../etc/config.pm"; }

# Globally require perl version
require 5.002;

# TCP port on which submitdaemon listens
our $SUBMITPORT   = 9147;
our $SUBMITSERVER = "square.a-eskwadraat.nl";

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

use Socket;
use IO;
use IO::Socket;
use File::Copy;
use File::Basename;
use File::Temp;
use File::stat;
use POSIX qw(strftime);

# Variables defining logmessages verbosity to stderr/logfile
my $verbose  = $ENV{SUBMITVERBOSE} || $LOG_NOTICE;
my $loglevel = $LOG_DEBUG;
my $logfile = "$ENV{HOME}/$USERSUBMITDIR/submit.log";
my $loghandle;
my $progname = basename($0);

# Variables for client-server communication
my $socket;
my $lastreply;

# Variables used in transmission.
my $problem;
my $language;
my $filename;
my $server = $SUBMITSERVER || $ENV{SUBMITSERVER} || "localhost";
my $team = $ENV{TEAM} || $ENV{USER} || $ENV{USERNAME};

my $tmpdir = "$ENV{HOME}/" . $USERSUBMITDIR;
my $tmpfile;
# Weer terug veranderen na debugging:
my $mask = 0744; # 0700

# variables for checking submission sanity.
my $userwarning = 0;
# Warn user when submission file modifications are older than (in minutes):
my $warn_mtime = 5;

sub logdate {
	return POSIX::strftime("%b %d %T",localtime);
}

sub logmsg {
	my $msglevel = shift;
	my $msgstring = "[".logdate()."] ".$progname."[$$]: @_\n";
	if ( $msglevel <= $verbose  ) { print STDERR     $msgstring; }
	if ( $msglevel <= $loglevel ) { print $loghandle $msgstring; }
}

# Forward declaration of 'sendit' for use in 'error'.
sub sendit;

sub error {
	if ( $socket ) {
		sendit "-error: @_";
		$socket->close();
	}
	logmsg($LOG_ERR,"error: @_");
	die;
}

sub netchomp {
	s/\015\012//;
}

sub sendit { # 'send' is already defined
	logmsg($LOG_DEBUG,"send: @_");
	print $socket "@_\015\012";
}

sub receive {
	if ( ! ($_ = <$socket>) ) { return $failure; }
	netchomp;
	if ( /^[^+]/ ) { error "received: $_"; }
	logmsg($LOG_DEBUG,"recv: $_");
	s/^.//;
	$lastreply = $_;
	return $success;
}

sub warnuser {
	print "WARNING: @_.\n";
	$userwarning++;
}

my $usage = <<"EOF";
Usage: $progname
              [--problem <problem>] [--lang <language>]
              [--server <server>]   [--team <team>]     <filename>
			  
For <problem> use the letter of the problem in lower- or uppercase.
The default for <problem> is the filename excluding the extension.
For example, 'c.java' will indicate problem 'C'.

For <language> use one of the following in lower- or uppercase:
   C:        c
   C++:      cc, cpp, c++
   Java:     java
   Pascal:   pas
   Haskell:  hs
The default for <language> is the extension of the filename.
For example, 'c.java' wil indicate a Java solution.

Example: $progname c.java
         $progname --problem e --lang cpp ProblemE.cc


The following options should not be needed, but are supported:

For <server> use the servername or IP-address of the submit-server.
The default value for <server> is defined internally or otherwise
taken from the environment variable 'SUBMITSERVER', or 'localhost'
if 'SUBMITSERVER' is not defined.

For <team> use the login of the account, you want to submit for.
The default value for <team> is taken from the environment variable
'TEAM' or your login name if 'TEAM' is not defined.

EOF
my $usage2 = "Type '$progname --help' to get help.\n";

########################
### Start of program ###
########################

open($loghandle,">> $logfile") or error "opening logfile '$logfile': $!";
$loghandle->autoflush(1);

# Parse options from command-line.
for (; @ARGV; shift @ARGV) {
	$_ = $ARGV[0];
	if ( /^--$/ || ! /^--.*/ ) { last; }
	elsif ( /^--team$/    ) { shift @ARGV; $team     = $ARGV[0]; }
	elsif ( /^--problem$/ ) { shift @ARGV; $problem  = $ARGV[0]; }
	elsif ( /^--lang$/    ) { shift @ARGV; $language = $ARGV[0]; }
	elsif ( /^--server$/  ) { shift @ARGV; $server   = $ARGV[0]; }
	elsif ( /^--help$/    ) { die $usage; }
	else { die "invalid option: '$ARGV[0]'.\n$usage2"; }
}

if ($#ARGV < 0) { die "Please specify a filename.\n$usage2" };

$filename = shift @ARGV;
if ( ! -r $filename ) { die "Cannot find file: '$filename'.\n$usage2"; }
logmsg($LOG_INFO,"filename is '$filename'");

# Check some file attributes and warn user.
if ( ! -f $filename ) { warnuser "'$filename' is not a regular file"; }
if (   -z $filename ) { warnuser "'$filename' is empty"; }
if ( ! -T $filename ) { warnuser "'$filename' seems not to be a text file"; }
if ( (time - stat($filename)->mtime) > ($warn_mtime * 60) ) {
	warnuser "'$filename' is last modified more than $warn_mtime minutes ago";
}

# If the problem was not specified, figure it out from the file name.
if ( ! defined $problem ) {
	if ( basename($filename) =~ /^([a-zA-Z0-9]*)(\..*)?$/ ) { $problem = $1; }
	else { die "No problem specified (as argument or in filename).\n$usage2" };
}
logmsg($LOG_INFO,"problem is '$problem'");

# If the language was not specified, figure it out from the file name.
if ( ! defined $language ) {
	$_ = $filename;
	if    ( /\.c$/i     ) { $language = "c" }
	elsif ( /\.cc$/i  ||
	        /\.cpp$/i ||
	        /\.c\+\+$/i ) { $language = "cpp" }
	elsif ( /\.java$/i  ) { $language = "java" }
	elsif ( /\.hs$/i    ) { $language = "haskell" }
	elsif ( /\.pas$/i   ) { $language = "pascal" }
	else { die "No language specified (as argument or in filename).\n$usage2"; }
}
logmsg($LOG_INFO,"language is '$language'");

if ( ! defined $team ) { die "No team specified.\n$usage2" };
logmsg($LOG_INFO,"team is '$team'");

if ( ! defined $server ) { die "No server specified.\n$usage2" };
logmsg($LOG_INFO,"server is '$server'");

# Make tempfile to submit.
if ( ! -d $tmpdir ) { mkdir($tmpdir) or error "creating dir $tmpdir: $!"; }
# Weer terug veranderen na debugging:
#chmod($mask,$tmpdir) or error  "setting permissions on $tmpdir: $!";

(my $handle, $tmpfile) = mkstemps("$tmpdir/$problem.XXXX",".$language")
	or error "creating tempfile: $!";

### Tijdelijk permissies van file aanpassen: ###
chmod($mask,$tmpfile);

copy($filename, $tmpfile) or error "copying '$filename' to tempfile: $!";
logmsg($LOG_INFO,"copied '$filename' to tempfile '$tmpfile'");

# Ask user for confirmation.
print "Submission information:\n";
print "  filename:   $filename\n";
print "  problem:    $problem\n";
print "  language:   $language\n";
print "  team:       $team\n";
print "  server:     $server\n";
if ( $userwarning > 0 ) { print "There are warnings for this submission!\n"; }
print "Do you want to continue? (y/n) ";
# Read characters from terminal one by one.
system("stty", '-icanon', 'eol', "\001");
while ( 1 ) {
	my $answer = getc(STDIN);
	if ( $answer =~ /y|n/i ) { print "\n"; }
	if ( $answer =~ /y/i   ) { last; }
	if ( $answer =~ /n/i   ) {
		unlink($tmpfile);
		error "submission aborted by user";
	}
}

# Connect to the submission server.
logmsg($LOG_NOTICE,"connecting to the server ($server, $SUBMITPORT/tcp)...");
$socket = IO::Socket::INET->new(Proto => 'tcp',
                                PeerAddr => $server,
                                PeerPort => $SUBMITPORT);
if ( ! $socket ) { error "cannot connect to the server"; }
$socket->autoflush;
logmsg($LOG_INFO,"connected");

receive;

# Send submission info.
logmsg($LOG_NOTICE,"sending data...");
sendit "+team $team";
receive;
sendit "+problem $problem";
receive;
sendit "+language $language";
receive;
sendit "+filename ".basename($tmpfile);
receive;
sendit "+done";
while ( receive ) {};

$socket->close();
logmsg($LOG_INFO,"connection closed");

unlink($tmpfile) or error "deleting '$tmpfile': $!";

logmsg($LOG_NOTICE,"submission successful");

print "Submission finished successfully.\n";
