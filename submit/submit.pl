#! /usr/bin/perl -w

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

# Some system/site specific config and common includes/functions
use config;
use submit_common;

use strict;

use Socket;
use IO;
use IO::Socket;
use File::Copy;
use File::Basename;
use File::Temp;

my $tmpdir = "$ENV{HOME}/" . $submitclientdir;
my $tmpfile;
# Weer terug veranderen na debugging:
my $mask = 0744; # 0700

my $verbose = $ENV{SUBMITVERBOSE} || 1;

my $progname = basename($0);

my $problem;
my $language;
my $filename;
my $server = $ENV{SUBMITSERVER} || "localhost";
my $team = $ENV{TEAM} || $ENV{USER} || $ENV{USERNAME};

sub logmsg {
	if ( $verbose ) { print STDERR "@_\n"; }
}

sub error {
	if ( -f $tmpfile ) { unlink $tmpfile; }
	die "$progname: error: @_\n"
}

my $usage = <<"EOF";
Usage: $progname
              [--problem <problem>] [--lang <language>]
              [--server <server>]   [--team <team>]     <filename>
			  
For <problem> use the letter of the problem in lower- or uppercase.
The default for <problem> is the filename excluding the extension.
For example, 'c.java' will indicate problem C.

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
The default value for <server> is taken from the environment variable
'SUBMITSERVER', or 'localhost' if 'SUBMITSERVER' is not defined.

For <team> use the login of the account, you want to submit for.
The default value for <team> is your login name.

EOF
my $usage2 = "Type '$progname --help' to get help.\n";

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
if ( ! -f $filename ) { die "Cannot find file: '$filename'.\n$usage2"; }
logmsg("filename is '$filename'");

# If the problem was not specified, figure it out from the file name.
if ( ! defined $problem ) {
	if ( basename($filename) =~ /(.+)\..*/ ) { $problem = $1; }
	else { die "No problem specified (as argument or in filename).\n$usage" };
}
logmsg "problem is '$problem'";

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
logmsg "language is '$language'";

if ( ! defined $team ) { die "No team specified.\n$usage2" };
logmsg "team is '$team'";

if ( ! defined $server ) { die "No server specified.\n$usage2" };
logmsg "server is '$server'";

# Do some checks on the submission and ask confirmation from user.
### TODO ###

# Make tempfile to submit.
if ( ! -d $tmpdir ) { mkdir($tmpdir) or error "creating dir $tmpdir: $!"; }
# Weer terug veranderen na debugging:
#chmod($mask,$tmpdir) or error  "setting permissions on $tmpdir: $!";

(my $handle, $tmpfile) = mkstemps("$tmpdir/$problem.XXXX",".$language")
	or error "creating tempfile: $!";

### Tijdelijk permissies van file aanpassen: ###
chmod($mask,$tmpfile);

copy($filename, $tmpfile) or error "copying '$filename' to tempfile: $!";
logmsg "'$filename' copied to tempfile '$tmpfile'";

# Connect to the submission server.
print "Connecting to the server ($server, $submitport/tcp)...\n";
logmsg "connecting...";
$socket = IO::Socket::INET->new(Proto => 'tcp',
                                PeerAddr => $server,
                                PeerPort => $submitport);
if ( ! $socket ) { error "cannot connect to the server"; }

$socket->autoflush;
logmsg "connected!";
receive;

# Send submission info.
print "Sending data...\n";
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
logmsg "connection closed";

unlink($tmpfile) or error "deleting '$tmpfile': $!";

print "Done: submission successful.\n";

logmsg "exiting";
