#!/usr/bin/perl -w

# jurydaemon.pl - Server for the submit program.
#
# Copyright (C) 1999, 2000 Eelco Dolstra (eelco@cs.uu.nl).
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
BEGIN { require "../etc/config.pm"; }

use Socket;
use IO;
use IO::Socket;
use Net::hostent;
use Carp;
use File::Copy;
use File::Basename;
use File::Temp;
use User::pwent;
use POSIX qw(strftime);

# Variables defining what to do with messages
my $verbose = 0;
my $log = 1;
my $progname = basename($0);

# Variables for client-server communication
my $server;
my $socket;
my $lastreply;

# Variables used in transmission.
my $team;
my $problem;
my $language;
my $filename;
my $ip;
my $tmpfile;

sub datestr {
	return POSIX::strftime("%b %d %T",localtime);
}

sub logmsg {
	if ( $verbose ) { print STDERR "@_\n"; }
	if ( $log ) { print "[".datestr()."] ".$progname."[$$]: @_\n"; }
}

# Forward declaration of 'sendit' for use in 'error'.
sub sendit;

sub error {
	if ( -f $tmpfile ) { unlink $tmpfile; }
	if ( $socket ) {
		sendit "-error: @_";
		$socket->close();
	}
	logmsg "error: @_";
	die "$progname: error: @_\n";
}

sub netchomp {
	s/\015\012//;
}

sub sendit { # 'send' is already defined
	logmsg "send: @_";
	print $socket "@_\015\012";
}

sub receive {
	if ( ! ($_ = <$socket>) ) { return $failure; }
	netchomp;
	if ( /^[^+]/ ) { error "received: $_"; }
	logmsg "recv: $_";
	s/^.//;
	$lastreply = $_;
	return $success;
}

# The reaper collects dead children.
my $waitedpid = 0;
sub REAPER {
	$waitedpid = wait;
	$SIG{CHLD} = \&REAPER;
	logmsg "reaped child $waitedpid".($? ? " with exitstatus $?" : "");
	return $success;
}
$SIG{CHLD} = \&REAPER;

# Fork off a child. The child executes the given code reference and dies.
# The parent returns immediately.
sub spawn {
	my $coderef = shift;

	if ( ! (@_ == 0 && $coderef && ref($coderef) eq 'CODE') ) {
		confess "usage: spawn CODEREF";
	}

	my $pid;
	if ( ! defined($pid = fork) ) {
		error "fork: $!";
		return;
	} elsif ( $pid ) {
		logmsg "spawned child $pid";
		return;
	}

	$SIG{CHLD} = 'DEFAULT';

	my $rc = &$coderef();

	logmsg "child going down";
	exit($rc ? 0 : 1);
}

sub child {
	my $hostinfo;
	my $handle;
	my $pw;

	$hostinfo = gethostbyaddr($socket->peeraddr());
	$ip = inet_ntoa($socket->peeraddr());

	logmsg "connection from ".($hostinfo->name || $socket->peerhost)." [$ip]";

	# Talk with the client: get submission info.
	sendit "+server ready";
	while ( receive ) {
		$_ = $lastreply;
		if      ( /^team\s+(\S+)\s*$/i ) {
			$team = $1;
			sendit "+received team '$team'";
		} elsif ( /^problem\s+(\S+)\s*$/i ) {
			$problem = lc $1;
			sendit "+received problem '$problem'";
		} elsif ( /^language\s+(\S+)\s*$/i ) {
			$language = lc $1;
			sendit "+received language '$language'";
		} elsif ( /^filename\s+(\S+)\s*$/i ) {
			$filename = $1;
			sendit "+received filename '$filename'";
		} elsif ( /^quit:?\s*(.*)$/i ) {
			logmsg "received quit: '$1'";
			$socket->close();
			return $failure;
		} elsif ( /^error:?\s*(.*)$/i ) {
			logmsg "received error: '$1'";
			$socket->close();
			return $failure;
		} elsif ( /^done\s*$/i ) {
			last;
		} else {
			error "invalid command: '$_'";
		}
	}

	# Check for succesful transmission of all info.
	if ( $lastreply ne "done" ) { error "connection lost"; }

	if ( ! ($problem && $team && $language && $filename) ) {
		error "missing submission data";
	}

	# Create the absolute path to submission file, which is expected
	# (and for security explicitly taken) to be basename only!
	$pw = getpwnam($team) or error "looking up username: $!";
	
	$filename = $pw->dir . "/$USERSUBMITDIR/" . basename($filename);

	($handle, $tmpfile) = mkstemps("$INCOMINGDIR/$problem.$team.XXXX",".$language")
		or error "creating tempfile: $!";
	logmsg "created tempfile: '".basename($tmpfile)."'";

	# Copy the source-file.
	### TODO: exitcode 0 bij authetication failure afvangen ###
	system(("scp","-Bq",$team.'@localhost:'.$filename,$tmpfile));
	if ( $? != 0 ) { error "copying file: exitcode $?"; }
#	copy("$filename","$tmpfile") or error "copying file: $!";
	logmsg "copied '$filename' to tempfile";
	
	# Check with database for correct parameters and then
	# add a database entry for this file.
	### TODO: basename($tmpfile) naar $tmpfile veranderen bij 'incoming' ###
	system(("./submit_db.php",$team,$ip,$problem,$language,basename($tmpfile)));
	if ( $? != 0 ) { error "adding to database: exitcode $?"; }
	logmsg "added submission to database";

	sendit "+submission successful";
	$socket->close();
	
	return $success;
}

########################
### Start of program ###
########################

logmsg "server started";

# Create the server socket.
$server = IO::Socket::INET->new(Proto => 'tcp',
                                LocalPort => $SUBMITPORT,
                                Listen => SOMAXCONN,
                                Reuse => 1)
	or die "cannot start server on port $SUBMITPORT/tcp";

logmsg "listening on port $SUBMITPORT/tcp";

# Accept connections and fork off children to handle them.
while ( 1 ) {
	if ( $socket = $server->accept() ) {
		logmsg "incoming connection, spawning child";
		spawn sub { child; };
		$socket->close();
	}
}

# Never reached.
logmsg "server going down";

