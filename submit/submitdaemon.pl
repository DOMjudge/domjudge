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

# Variables defining logmessages verbosity to stderr/logfile
my $verbose  = $LOG_NOTICE;
my $loglevel = $LOG_DEBUG;
my $logfile = "$LOGDIR/submit.log";
my $loghandle;
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
	logmsg($LOG_ERROR,"error: @_");
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

# The reaper collects dead children.
my $waitedpid = 0;
sub REAPER {
	$waitedpid = wait;
	$SIG{CHLD} = \&REAPER;
	logmsg($LOG_INFO,
		   "reaped child $waitedpid".($? ? " with exitstatus $?" : ""));
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
		logmsg($LOG_INFO,"spawned child $pid");
		return;
	}

	$SIG{CHLD} = 'DEFAULT';

	my $rc = &$coderef();

	logmsg($LOG_INFO,"child going down");
	exit($rc ? 0 : 1);
}

sub child {
	my $hostinfo;
	my $handle;
	my $pw;

	$hostinfo = gethostbyaddr($socket->peeraddr());
	$ip = inet_ntoa($socket->peeraddr());

	logmsg($LOG_NOTICE,
		   "connection from ".($hostinfo->name || $socket->peerhost)." [$ip]");

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
			logmsg($LOG_NOTICE,"received quit: '$1'");
			$socket->close();
			return $failure;
		} elsif ( /^error:?\s*(.*)$/i ) {
			logmsg($LOG_NOTICE,"received error: '$1'");
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
	logmsg($LOG_NOTICE,"submission received: $team/$problem/$language");

	# Create the absolute path to submission file, which is expected
	# (and for security explicitly taken) to be basename only!
	$pw = getpwnam($team) or error "looking up username: $!";
	
	$filename = $pw->dir . "/$USERSUBMITDIR/" . basename($filename);

	($handle, $tmpfile) = mkstemps("$INCOMINGDIR/$problem.$team.XXXX",".$language")
		or error "creating tempfile: $!";
	logmsg($LOG_INFO,"created tempfile: '".basename($tmpfile)."'");

	# Copy the source-file.
	system(("./submit_copy.sh",$team,$filename,$tmpfile));
	if ( $? != 0 ) { error "copying file: exitcode $?"; }
	logmsg($LOG_INFO,"copied '$filename' to tempfile");
	
	# Check with database for correct parameters and then
	# add a database entry for this file.
	system(("./submit_db.php",$team,$ip,$problem,$language,basename($tmpfile)));
	if ( $? != 0 ) { error "adding to database: exitcode $?"; }
	logmsg($LOG_INFO,"added submission to database");

	unlink($tmpfile) or error "deleting '$tmpfile': $!";

	sendit "+submission successful";
	$socket->close();
	
	return $success;
}

########################
### Start of program ###
########################

open($loghandle,">> $logfile") or error "opening logfile '$logfile': $!";
$loghandle->autoflush(1);

logmsg($LOG_NOTICE,"server started");
logmsg($LOG_DEBUG,
	   "verbose = $verbose, loglevel = $loglevel, logfile = $logfile");

# Create the server socket.
$server = IO::Socket::INET->new(Proto => 'tcp',
                                LocalPort => $SUBMITPORT,
                                Listen => SOMAXCONN,
                                Reuse => 1)
	or error "cannot start server on port $SUBMITPORT/tcp";

logmsg($LOG_INFO,"listening on port $SUBMITPORT/tcp");

# Accept connections and fork off children to handle them.
while ( 1 ) {
	if ( $socket = $server->accept() ) {
		logmsg($LOG_INFO,"incoming connection, spawning child");
		spawn sub { child; };
		$socket->close();
	}
}

# Never reached.
logmsg($LOG_NOTICE,"server going down");
