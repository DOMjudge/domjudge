#!/bin/bash

# Script to test (compile, run and compare) solutions.
# Copyright (C) 2004 Jaap Eldering (eldering@a-eskwadraat.nl).
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


# Usage: $0 <source> <lang> <testdata.in> <testdata.out> <timelimit> <tmpdir>
#
# <source>        File containing source-code.
# <lang>          Language of the source, see config-file for details.
# <testdata.in>   File containing test-input.
# <testdata.out>  File containing test-output.
# <timelimit>     Timelimit in seconds.
# <tmpdir>        Directory where to execute solution in a chroot-ed
#                 environment. For best security leave it as empty as possible.
#                 Certainly do not place output-files there!
#
# This script supports languages, by calling separate compile scripts
# depending on <lang>, namely 'compile_<lang>.sh'. These compile scripts
# should compile the source to a statically linked, standalone executable!
# Syntax for these compile scripts is:
#
#   compile_<lang>.sh <source> <dest>
#
# where <dest> will be the same filename as <source> but without extension.
#
# For running the solution a script 'run.sh' is called. For usage of
# 'run.sh' see that script.

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error ERR
trap cleanexit EXIT

function logdate ()
{
	date '+%b %d %T'
}

function logmsg ()
{
	local msglevel msgstring
	msglevel=$1; shift
	msgstring="[`logdate`] $PROGNAME[$$]: $@"
	if [ $msglevel -le "$VERBOSE"  ]; then echo "$msgstring" >&2 ; fi
	if [ $msglevel -le "$LOGLEVEL" ]; then echo "$msgstring" >>$LOGFILE ; fi
}

function cleanexit ()
{
	trap - EXIT

	if [ "$CATPID" ] && ps --pid $CATPID &>/dev/null; then
		logmsg $LOG_DEBUG "killing $CATPID (cat-pipe to /dev/null)"
		kill -9 $CATPID
	fi

	logmsg $LOG_INFO "exiting"
}

function error ()
{
	set +e
	trap - ERR

	if [ "$@" ]; then
		logmsg $LOG_ERR "error: $@"
	else
		logmsg $LOG_ERR "unexpected error, aborting!"
	fi

	exit $E_INTERN
}

# Global configuration
source "`dirname $0`/../etc/config.sh"

# Logging:
LOGFILE="$LOGDIR/judge.`hostname --short`.log"
LOGLEVEL=$LOG_DEBUG
PROGNAME="`basename $0`"

# Set this for extra verbosity:
#VERBOSE=$LOG_DEBUG
if [ "$VERBOSE" ]; then
	export VERBOSE
else
	export VERBOSE=$LOG_ERR
fi

# Location of scripts/programs:
RUNSCRIPTDIR="$SYSTEM_ROOT/judge"
BASHSTATIC="$SYSTEM_ROOT/bin/bash-static"
RUNGUARD="$SYSTEM_ROOT/bin/runguard"

logmsg $LOG_NOTICE "starting '$0', PID = $$"

[ $# -eq 6 ] || error "wrong number of arguments. see script-code for usage."
SOURCE="$1";    shift
PROGLANG="$1";  shift
TESTIN="$1";    shift
TESTOUT="$1";   shift
TIMELIMIT="$1"; shift
TMPDIR="$1";    shift
logmsg $LOG_INFO "arguments: '$SOURCE' '$PROGLANG' '$TESTIN' '$TESTOUT' '$TIMELIMIT' '$TMPDIR'"

[ -r "$SOURCE"  ] || error "solution not found: $SOURCE";
[ -r "$TESTIN"  ] || error "test-input not found: $TESTIN";
[ -r "$TESTOUT" ] || error "test-ouput not found: $TESTOUT";
[ -d "$TMPDIR" -a -w "$TMPDIR" -a -x "$TMPDIR" ] || \
	error "Tempdir not found or not writable: $TMPDIR"

logmsg $LOG_NOTICE "setting resource limits"
ulimit -HS -c 0     # Do not write core-dumps
ulimit -HS -f 65536 # Maximum filesize in kB

logmsg $LOG_NOTICE "creating input/output files"
EXT="${SOURCE##*.}"
[ "$EXT" ] || error "source-file does not have an extension: $SOURCE"
cp "$SOURCE" "$TMPDIR/source.$EXT"
cp "$TESTIN" "$TMPDIR/testdata.in"

OLDDIR="$PWD"
cd "$TMPDIR"

# Create files, which are expected to exist:
touch compile.{out,time}   # Compiler output and runtime
touch error.out            # Error output after compiler output
touch diff.out             # Compare output
touch program.{out,err}    # Program output and stderr (for extra information)
touch program.{time,exit}  # Program runtime and exitcode

# program.{out,err,time,exit} are written to by processes running as RUNUSER:
chmod a+rw program.{out,err,time,exit}


logmsg $LOG_NOTICE "starting compile"

if [ `cat source.$EXT | wc -c` -gt $((SOURCESIZE*1024)) ]; then
	echo "Source-code is larger than $SOURCESIZE kB." >>compile.out
	exit $E_COMPILE
fi

# First compile to 'source' then rename to 'program' to avoid problems with
# the compiler writing to different filenames and deleting intermediate files.
( "$RUNGUARD" -t $COMPILETIME -o compile.time \
	"$RUNSCRIPTDIR/compile_$PROGLANG.sh" "source.$EXT" source
) &>compile.tmp
exitcode=$?
[ -f source ] && mv -f source program

logmsg $LOG_DEBUG "checking compilation exit-status"
if grep 'timelimit reached: aborting command' compile.tmp &>/dev/null; then
	echo "Compiling aborted after $COMPILETIME seconds." >compile.out
	exit $E_COMPILE
fi
if [ $exitcode -ne 0 ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	exit $E_COMPILE
fi
cat compile.tmp >>compile.out


logmsg $LOG_NOTICE "setting up chroot-ed environment"

mkdir bin dev proc
# Copy the run-script and a statically compiled bash-shell:
cp -p "$RUNSCRIPTDIR/run.sh" .
cp -p "$BASHSTATIC"          ./bin/bash

# Mount (bind) the proc filesystem (needed by Java for /proc/self/stat):
logmsg $LOG_DEBUG "mounting proc filesystem"
sudo mount -n -t proc --bind /proc proc

logmsg $LOG_DEBUG "making a fifo-buffer link to /dev/null"
mkfifo -m a+rw ./dev/null
cat < ./dev/null >/dev/null &
CATPID=$!
disown $CATPID


logmsg $LOG_NOTICE "running program"

( "$RUNGUARD" -r "$PWD" -u "$RUNUSER" -t $TIMELIMIT -o program.time \
	/run.sh /program testdata.in program.out program.err program.exit \
	        $MEMLIMIT $FILELIMIT $PROCLIMIT ) &>error.tmp
exitcode=$?

logmsg $LOG_DEBUG "unmounting proc filesystem"
sudo umount "$PWD/proc"

# Check for still running processes (first wait for all exiting processes):
sleep 1
if ps -u "$RUNUSER" &>/dev/null; then
	error "found processes still running"
fi

# Append (heading/trailing) program stderr to error.tmp:
echo "*** Program stderr output following (first and last 10 lines) ***" >>error.tmp
if [ `cat program.err | wc -l` -gt 20 ]; then
	head -n 10 program.err >>error.tmp
	tail -n 10 program.err >>error.tmp
else
	cat program.err >>error.tmp
fi

# Check for errors from running the program:
logmsg $LOG_DEBUG "checking program run exit-status"
if grep  'timelimit reached: aborting command' error.tmp &>/dev/null; then
	echo "Timelimit exceeded." >>error.out
	cat error.tmp >>error.out
	exit $E_TIMELIMIT
fi
if [ ! -r program.exit ]; then
	cat error.tmp >>error.out
	error "'program.exit' not readable"
fi
if [ "`cat program.exit`" != "0" ]; then
	echo "Non-zero exitcode `cat program.exit`" >>error.out
	cat error.tmp >>error.out
	exit $E_RUNERROR
fi
if [ $exitcode -ne 0 ]; then
	cat error.tmp >>error.out
	error "exitcode $exitcode without program.exit != 0"
fi

############################################################
### Checks for other runtime errors:                     ###
### Removed, because these are not consistently reported ###
### the same way by all different compilers.             ###
############################################################
#if grep  'Floating point exception' error.tmp &>/dev/null; then
#	echo "Floating point exception." >>error.out
#	exit $E_RUNERROR
#fi
#if grep  'Segmentation fault' error.tmp &>/dev/null; then
#	echo "Segmentation fault." >>tee error.out
#	exit $E_RUNERROR
#fi


logmsg $LOG_NOTICE "comparing output"

# Copy testdata output (first cd to olddir to correctly resolve relative paths)
cd "$OLDDIR"
cp "$TESTOUT" "$TMPDIR/testdata.out"
cd "$TMPDIR"

if [ ! -s program.out ]; then
	echo "Program produced no output." >>error.out
	cat error.tmp >>error.out
	exit $E_OUTPUT
fi

# Add $SYSTEM_ROOT/bin to path for 'tempfile' (needed by compare.sh)
export PATH="$SYSTEM_ROOT/bin:$PATH"

"$RUNSCRIPTDIR/compare.sh" program.out testdata.out diff.out 2>diff.tmp
exitcode=$?

if [ $exitcode -ne 0 ]; then
	cat error.tmp >>error.out
	error "diff: `cat diff.tmp`";
fi
if [ -s diff.out ]; then
	echo "Wrong answer." >>error.out
	cat error.tmp >>error.out
	exit $E_ANSWER
fi

echo "Correct!" >>error.out
cat error.tmp >>error.out
exit $E_CORRECT
