#!/usr/bin/perl -w

# submit.pl - Submit program.
#
# Copyright (C) 1999 Eelco Dolstra (eelco@cs.uu.nl).
# Copyright (C) 2004 Jaap Eldering (eldering@a-eskwadraat.nl),
#                    Thijs Kinkhorst,
#                    Peter van de Werken.
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
use IO::Handle;
use IO::Socket;
use File::Copy;
use File::Basename;
use File::Temp;
use File::stat;
use POSIX qw(:termios_h strftime);
use Getopt::Long;

# Variables defining where/how to store files.
my $submitdir = "$ENV{HOME}/$USERSUBMITDIR";
my $tmpfile;
### Tijdelijk voor DS-practicum (moet normaal 0700/0600 zijn):
my $permdir  = 0711;
my $permfile = 0644;

# Variables defining logmessages verbosity to stderr/logfile
my $verbose  = $LOG_DEBUG;
my $loglevel = $LOG_DEBUG;
my $logfile = "$submitdir/submit.log";
my $loghandle;
my $progname = basename($0);
my $quiet = 0;

# Variables for client-server communication
my $socket;
my $lastreply;

# Variables used in transmission.
my $problem;
my $language;
my $filename;
my $server = $SUBMITSERVER || $ENV{SUBMITSERVER} || "localhost";
my $team = $ENV{TEAM} || $ENV{USER} || $ENV{USERNAME};

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
		$socket = '';
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
	logmsg($LOG_DEBUG,"recv: $_");
	if ( /^[^+]/ ) {
		$socket->close();
		$socket = '';
		s/-?(error: )?//;
		error "$_";
	}
	s/^.//;
	$lastreply = $_;
	return $success;
}

sub warnuser {
	if ( ! $quiet ) { print "WARNING: @_.\n"; }
	$userwarning++;
}

### Copied inline from HotKey.pm ###
my ($term, $oterm, $echo, $noecho, $fd_stdin);

$fd_stdin = fileno(STDIN);
$term     = POSIX::Termios->new();
$term->getattr($fd_stdin);
$oterm     = $term->getlflag();

$echo     = ECHO | ECHOK | ICANON;
$noecho   = $oterm & ~$echo;

sub cbreak {
	$term->setlflag($noecho);  # ok, so i don't want echo either
	$term->setcc(VTIME, 1);
	$term->setattr($fd_stdin, TCSANOW);
}

sub cooked {
	$term->setlflag($oterm);
	$term->setcc(VTIME, 0);
	$term->setattr($fd_stdin, TCSANOW);
}

sub readkey {
	my $key = '';
	cbreak();
	sysread(STDIN, $key, 1);
	cooked();
	return $key;
}

END { cooked() }
### End of HotKey.pm ###

sub readanswer {
	my $answers = shift;
	my $answer;
	while ( "TRUE" ) {
		chomp($answer = <STDIN>);
		if ( $answer =~ /[$answers]/i   ) { last; }
	}
	return lc($answer);
}

my $usage = <<"EOF";
Usage: $progname [OPTION]... FILENAME
Submit a solution for a problem.

Options (see below for more information)
  -p, --problem=PROBLEM    submit for problem PROBLEM
  -l, --language=LANGUAGE  submit in language LANGUAGE
  -s, --server=SERVER      submit to server SERVER
  -t, --team=TEAM          submit as team TEAM
  -v, --verbose[=LEVEL]	   set verbosity to LEVEL, where LEVEL must be
                               numerically specified as in 'syslog.h'
                               defaults to LOG_INFO without argument
  -q, --quiet              set verbosity to LOG_ERR and suppress user
                               input and warning/info messages
      --help               display this help and exit
      --version            output version information and exit

Explanation of submission options:

For PROBLEM use the ID of the problem (letter, number or short name)
in lower- or uppercase. When not specified, PROBLEM defaults to
FILENAME excluding the extension.
For example, 'c.java' will indicate problem 'C'.

For LANGUAGE use one of the following in lower- or uppercase:
   C:        c
   C++:      cc, cpp, c++
   Java:     java
   Pascal:   pas
The default for LANGUAGE is the extension of FILENAME.
For example, 'c.java' wil indicate a Java solution.

Examples:

Submit problem 'c' in Java:
	$progname c.java

Submit problem 'e' in C++:
	$progname --problem e --language=cpp ProblemE.cc

Submit problem 'hello' in C (options override the defaults from FILENAME):
	$progname -p hello -l C HelloWorld.java


The following options should normally not be needed:

For SERVER use the servername or IP-address of the submit-server.
The default value for SERVER is defined internally or otherwise
taken from the environment variable 'SUBMITSERVER', or 'localhost'
if 'SUBMITSERVER' is not defined.

For TEAM use the login of the account, you want to submit for.
The default value for TEAM is taken from the environment variable
'TEAM' or your login name if 'TEAM' is not defined.

EOF
my $usage2 = "Type '$progname --help' to get help.\n";

my $version = <<"EOF";
submit
Copyright (C) 2004 Jaap Eldering, Thijs Kinkhorst and Peter van de Werken

This is free software; see the source for copying conditions.  There is NO
warranty; not even for MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
EOF

########################
### Start of program ###
########################

# Make directory where to store logfile and tempfiles for submission.
if ( ! -d $submitdir ) { mkdir($submitdir) or error "creating dir $submitdir: $!"; }
chmod($permdir,$submitdir) or error "setting permissions on $submitdir: $!";

open($loghandle,">> $logfile") or error "opening logfile '$logfile': $!";
$loghandle->autoflush(1);

# Voor het DS-practicum: check of homedir executable is
if ( (stat($ENV{HOME})->mode & $permdir) != $permdir ) {

	print <<"EOF";
WAARSCHUWING:

Voor dit practicum is het noodzakelijk dat je homedir toegankelijk
is voor de jury, om de source-code bestanden van je inzending te
kunnen kopieeren.

Deze permissies zullen nu ingesteld worden op je home-directory.
Wil je doorgaan? (j/n) 
EOF

	if ( readanswer('jn') eq 'n' ) { die "Afgebroken: permissies niet aangepast.\n"; }
	
	chmod($permdir,$ENV{HOME}) or error "setting permissions on $ENV{HOME}: $!";
	print "Permissies van $ENV{HOME} aangepast!\n\n";
}


my $show_help = 0;
my $show_version = 0;
eval {
	GetOptions('team|t=s'     => \$team,
	           'problem|p=s'  => \$problem,
	           'language|l=s' => \$language,
	           'server|s=s'   => \$server,
	           'verbose|v=i'  => \$verbose,
			   'quiet|q' => sub { $quiet = 1; $verbose = $LOG_ERR; },
	           'help'    => sub { $show_help = 1; },
	           'version' => sub { $show_version = 1; });
} or die "$@$usage2";

if ( $show_help )    { print $usage;   exit; }
if ( $show_version ) { print $version; exit; }

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
#	elsif ( /\.hs$/i    ) { $language = "haskell" }
	elsif ( /\.pas$/i   ) { $language = "pascal" }
	else { die "No language specified (as argument or in filename).\n$usage2"; }
}
logmsg($LOG_INFO,"language is '$language'");

if ( ! defined $team ) { die "No team specified.\n$usage2" };
logmsg($LOG_INFO,"team is '$team'");

if ( ! defined $server ) { die "No server specified.\n$usage2" };
logmsg($LOG_INFO,"server is '$server'");

# Make tempfile to submit.
(my $handle, $tmpfile) = mkstemps("$submitdir/$problem.XXXX",".$language")
	or error "creating tempfile: $!";

copy($filename, $tmpfile) or error "copying '$filename' to tempfile: $!";
chmod($permfile,$tmpfile) or error "setting permissions on $tmpfile: $!";
logmsg($LOG_INFO,"copied '$filename' to tempfile '$tmpfile'");

# Ask user for confirmation.
if ( ! $quiet ) {
	print "Submission information:\n";
	print "  filename:   $filename\n";
	print "  problem:    $problem\n";
	print "  language:   $language\n";
	print "  team:       $team\n";
	print "  server:     $server\n";
	if ( $userwarning > 0 ) { print "There are warnings for this submission!\n"; }
	print "Do you want to continue? (y/n)\n";
	if ( readanswer('yn') eq 'n' ) {
		unlink($tmpfile);
		logmsg($LOG_INFO,"submission aborted by user");
		die "Submission aborted by user.\n";
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

if ( ! ( $lastreply =~ /^done/ ) ) { error "connection closed unexpected"; }

$socket->close();
$socket = '';
logmsg($LOG_INFO,"connection closed");

unlink($tmpfile) or error "deleting '$tmpfile': $!";

logmsg($LOG_NOTICE,"submission successful");

print "Submission finished successfully.\n";
