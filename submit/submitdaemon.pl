#! /usr/bin/perl -w

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
	if ( $log ) { print "[", datestr, "] ", $progname, "[$$]: @_\n"; }
}

sub error {
	if ( -f $tmpfile ) { unlink $tmpfile; }
	logmsg "error: @_";
	die "$progname: error: @_\n"
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
	if ( /^[^+]/ ) { error("received: $_\n"); }
	logmsg("recv: $_");
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

	# Talk with the client: get submission info & transfer file.
	sendit "+server ready";
	while ( receive ) {
		$_ = $lastreply;
		if      ( /^team\s+(\S+)\s*$/i ) {
			$team = $1;
			sendit "+received team $team";
		} elsif ( /^problem\s+(\S+)\s*$/i ) {
			$problem = lc $1;
			sendit "+received problem $problem";
		} elsif ( /^language\s+(\S+)\s*$/i ) {
			$language = lc $1;
			sendit "+received language $language";
		} elsif ( /^filename\s+(\S+)\s*$/i ) {
			$filename = $1;
			sendit "+received filename $filename";
		} elsif ( /^done\s*$/i ) {
			last;
		} elsif ( /^quit\s*$/i ) {
			sendit "-received quit, aborting";
			return $failure;
		} else {
			sendit "-invalid command";
		}
	}

	if ( $lastreply ne "done" ) { error "connection lost"; }

	# Did we get all required info?
	if ( ! ($problem && $team && $language && $filename) ) {
		sendit "-missing data, aborting";
		error "missing submission data";
	}

	# Create the absolute path to submission file, which is expected
	# (and for security explicitly taken) to be basename only!
	if ( ! ($pw = getpwnam($team)) ) {
		sendit "-invalid username";
		error "looking up username";
	}
	$filename = $pw->dir . "/$submitclientdir/" . basename($filename);

	($handle, $tmpfile) = mkstemps("$submitserverdir/$problem.$team.XXXX",".$language")
		or error "creating tempfile: $!";

	# Check parameters with database: does the language exist, team matches IP,
	# is the problem active and submittable?
	system(("./submit_checkvars.php",$team,$ip,$problem,$language,$tmpfile));

	if ( $? != 0 ) {
		sendit "-error checking parameters";
		error "invalid submission parameters";
	}
	
	# if that's ok, copy the file
	if( ! copy("$filename","$tmpfile") ) {
		my $errno = $!;
		sendit "-error copying file";
		$! = $errno;
		error "copying file: $!";
	}
	logmsg "submission copied to '" . basename($tmpfile) . "'";
	
	# add a db-entry for this file.
	system(("./submit_db.php",$team,$ip,$problem,$language,basename($tmpfile)));
	if ( $? != 0 ) {
		sendit "-error adding to database";
		error "adding to database";
	}

	sendit "+done submission successful";
	logmsg "submission received successfully";

	$socket->close();
	
	return $success;
}

########################
### Start of program ###
########################

logmsg "server started";

# Create the server socket.
$server = IO::Socket::INET->new(Proto => 'tcp',
                                LocalPort => $submitport,
                                Listen => SOMAXCONN,
                                Reuse => 1)
	or die "cannot start server on port $submitport/tcp";

logmsg "listening on port $submitport/tcp";

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

