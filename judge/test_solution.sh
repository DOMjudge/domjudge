#!/bin/bash

# Script to test (compile, run and compare) solutions.
# Copyright (C) 2004--2006 Jaap Eldering (eldering@a-eskwadraat.nl).
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
# Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


# Usage: $0 <source> <lang> <testdata.in> <testdata.out> <timelimit> <tmpdir>
#           [<special-run> [<special-compare>]]
#
# <source>          File containing source-code.
# <lang>            Language of the source, see config-file for details.
# <testdata.in>     File containing test-input.
# <testdata.out>    File containing test-output.
# <timelimit>       Timelimit in seconds.
# <tmpdir>          Directory where to execute solution in a chroot-ed
#                   environment. For best security leave it as empty as possible.
#                   Certainly do not place output-files there!
# <special-run>     Extension name of specialized run or compare script to use.
# <special-compare> Specify empty string for <special-run> if only
#                   <special-compare> is to be used. The script
#                   'run_<special-run>.sh' or 'compare_<special-compare>.sh'
#                   will be called if argument is non-empty.
#
# This script supports languages, by calling separate compile scripts
# depending on <lang>, namely 'compile_<lang>.sh'. These compile scripts
# should compile the source to a statically linked, standalone executable!
# Syntax for these compile scripts is:
#
#   compile_<lang>.sh <source> <dest> <memlimit>
#
# where <dest> is the same filename as <source> but without extension.
# The <memlimit> (in kB) is passed to the compile script, to let
# interpreted languages (read: Sun javac/java) be able to set the
# internal maximum memory size.
#
# For running the solution a script 'run.sh' is called (default). For
# usage of 'run.sh' see that script. Likewise, for comparing results, a
# script 'compare.sh' is called by default.
#
# If 'xsltproc' is available, then the result is parsed from
# 'result.xml' according to the ICPC Validator Interface Standard as
# described in http://www.ecs.csus.edu/pc2/doc/valistandard.html.
# Otherwise a submission is counted as accepted when the filesize of
# compare.out is zero. In both cases, if the compare script returns with
# nonzero exitcode, this is viewed as an internal error.


# Global configuration
source "`dirname $0`/../etc/config.sh"

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error ERR
trap cleanexit EXIT

cleanexit ()
{
	trap - EXIT

	if [ "$CATPID" ] && ps --pid $CATPID &>/dev/null; then
		logmsg $LOG_DEBUG "killing $CATPID (cat-pipe to /dev/null)"
		kill -9 $CATPID
	fi

	# Remove copied bash-static to save disk space
	rm -f "$TMPDIR/bin/bash"

	logmsg $LOG_INFO "exiting"
}

# Error and logging functions
source "$SYSTEM_ROOT/lib/lib.error.sh"

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
SCRIPTDIR="$SYSTEM_ROOT/judge"
BASHSTATIC="$SYSTEM_ROOT/bin/bash-static"
RUNGUARD="$SYSTEM_ROOT/bin/runguard"

logmsg $LOG_NOTICE "starting '$0', PID = $$"

[ $# -ge 6 ] || error "not enough of arguments. see script-code for usage."
SOURCE="$1";    shift
PROGLANG="$1";  shift
TESTIN="$1";    shift
TESTOUT="$1";   shift
TIMELIMIT="$1"; shift
TMPDIR="$1";    shift
SPECIALRUN="$1";
SPECIALCOMPARE="$2";
logmsg $LOG_INFO "arguments: '$SOURCE' '$PROGLANG' '$TESTIN' '$TESTOUT' '$TIMELIMIT' '$TMPDIR'"
logmsg $LOG_INFO "optionals: '$SPECIALRUN' '$SPECIALCOMPARE'"

COMPILE_SCRIPT="$SCRIPTDIR/compile_$PROGLANG.sh"
COMPARE_SCRIPT="$SCRIPTDIR/compare${SPECIALCOMPARE:+_$SPECIALCOMPARE}.sh"
RUN_SCRIPT="run${SPECIALRUN:+_$SPECIALRUN}.sh"

[ -r "$SOURCE"  ] || error "solution not found: $SOURCE"
[ -r "$TESTIN"  ] || error "test-input not found: $TESTIN"
[ -r "$TESTOUT" ] || error "test-output not found: $TESTOUT"
[ -d "$TMPDIR" -a -w "$TMPDIR" -a -x "$TMPDIR" ] || \
	error "Tempdir not found or not writable: $TMPDIR"
[ -r "$COMPILE_SCRIPT" ] || error "compile script not found: $COMPILE_SCRIPT"
[ -r "$COMPARE_SCRIPT" ] || error "compare script not found: $COMPARE_SCRIPT"
[ -r "$SCRIPTDIR/$RUN_SCRIPT" ] || error "run script not found: $RUN_SCRIPT"

logmsg $LOG_NOTICE "setting resource limits"
ulimit -HS -c 0     # Do not write core-dumps
ulimit -HS -f 65536 # Maximum filesize in kB

logmsg $LOG_NOTICE "creating input/output files"
EXT="${SOURCE##*.}"
[ "$EXT" ] || error "source-file does not have an extension: $SOURCE"
cp "$SOURCE" "$TMPDIR/source.$EXT"

OLDDIR="$PWD"
cd "$TMPDIR"

# Check whether we're going to run in a chroot environment:
if [ -z "$USE_CHROOT" ] || [ "$USE_CHROOT" -eq 0 ]; then
# unset to allow shell default parameter substitution on USE_CHROOT:
	unset USE_CHROOT
	PREFIX=$PWD
else
	PREFIX=''
fi

# Make testing dir accessible for RUNUSER:
chmod a+x $TMPDIR

# Create files which are expected to exist:
touch compile.out compile.time   # Compiler output and runtime
touch error.out                  # Error output after compiler output
touch compare.out                # Compare output
touch result.xml result.out      # Result of comparison (XML and plaintext version)
touch program.out program.err    # Program output and stderr (for extra information)
touch program.time program.exit  # Program runtime and exitcode

# program.{out,err,time,exit} are written to by processes running as RUNUSER:
chmod a+rw program.out program.err program.time program.exit

# Make source readable (for if it is interpreted):
chmod a+r source.$EXT

logmsg $LOG_NOTICE "starting compile"

if [ `cat source.$EXT | wc -c` -gt $(($SOURCESIZE*1024)) ]; then
	echo "Source-code is larger than $SOURCESIZE kB." >>compile.out
	exit $E_COMPILE
fi

# First compile to 'source' then rename to 'program' to avoid problems with
# the compiler writing to different filenames and deleting intermediate files.
( "$RUNGUARD" -t $COMPILETIME -o compile.time \
	"$COMPILE_SCRIPT" "source.$EXT" source "$MEMLIMIT" ) &>compile.tmp
exitcode=$?
if [ -f source ]; then
    mv -f source program
    chmod a+rx program
fi

logmsg $LOG_DEBUG "checking compilation exit-status"
if grep 'timelimit reached: aborting command' compile.tmp &>/dev/null; then
	echo "Compiling aborted after $COMPILETIME seconds." >compile.out
	exit $E_COMPILE
fi
if [ $exitcode -ne 0 -o ! -e program ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	exit $E_COMPILE
fi
cat compile.tmp >>compile.out


logmsg $LOG_NOTICE "setting up testing (chroot) environment"

# Copy the testdata input (only after compilation to prevent information leakage)
cd "$OLDDIR"
cp "$TESTIN" "$TMPDIR/testdata.in"
cd "$TMPDIR"
chmod a+r testdata.in

mkdir --mode=0711 bin dev proc
# Copy the run-script and a statically compiled bash-shell:
cp -p "$SCRIPTDIR/$RUN_SCRIPT" .
cp -p "$BASHSTATIC"            ./bin/bash
chmod a+rx "$RUN_SCRIPT" bin/bash

# Execute an optional chroot setup script:
if [ "$USE_CHROOT" -a "$CHROOT_SCRIPT" ]; then
	logmsg $LOG_DEBUG "executing chroot script: '$CHROOT_SCRIPT start'"
	$CHROOT_SCRIPT start
fi

logmsg $LOG_DEBUG "making a fifo-buffer link to /dev/null"
mkfifo -m a+rw ./dev/null
cat < ./dev/null >/dev/null &
CATPID=$!
disown $CATPID

# Run the solution program (within a restricted environment):
logmsg $LOG_NOTICE "running program (USE_CHROOT = ${USE_CHROOT:-0})"

( "$RUNGUARD" ${USE_CHROOT:+-r "$PWD"} -u "$RUNUSER" -t $TIMELIMIT -o program.time -- \
	$PREFIX/$RUN_SCRIPT $PREFIX/program testdata.in program.out program.err program.exit \
		$MEMLIMIT $FILELIMIT $PROCLIMIT ) &>error.tmp
exitcode=$?

# Execute an optional chroot destroy script:
if [ "$USE_CHROOT" -a "$CHROOT_SCRIPT" ]; then
	logmsg $LOG_DEBUG "executing chroot script: '$CHROOT_SCRIPT stop'"
	$CHROOT_SCRIPT stop
fi

# Check for still running processes (first wait for all exiting processes):
sleep 1
if ps -u "$RUNUSER" &>/dev/null; then
	error "found processes still running"
fi

# Append (heading/trailing) program stderr to error.tmp:
if [ `cat program.err | wc -l` -gt 20 ]; then
	echo "*** Program stderr output following (first and last 10 lines) ***" >>error.tmp
	head -n 10 program.err >>error.tmp
	echo "*** <snip> ***"  >>error.tmp
	tail -n 10 program.err >>error.tmp
else
	echo "*** Program stderr output following ***" >>error.tmp
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

logmsg $LOG_INFO "starting script '$COMPARE_SCRIPT'"

if ! "$COMPARE_SCRIPT" testdata.in program.out testdata.out \
                       result.xml compare.out &>compare.tmp ; then
	exitcode=$?
	cat error.tmp >>error.out
	error "compare exited with exitcode $exitcode: `cat compare.tmp`";
fi

# Parse result.xml if 'xsltproc' is available, otherwise check for empty
# compare output
if [ -x `which xsltproc` ]; then
	xsltproc $SCRIPTDIR/parse_result.xslt result.xml > result.out
	result=`grep '^result='      result.out | cut -d = -f 2- | tr '[:upper:]' '[:lower:]'`
	descrp=`grep '^description=' result.out | cut -d = -f 2-`
else
	if [ -s compare.out ]; then
		result="wrong anser"
	else
		result="accepted"
	fi
fi
descrp="${descrp:+ ($descrp)}"

if [ "$result" = "accepted" ]; then
	echo "Correct${descrp}! Runtime is `cat program.time` seconds." >>error.out
	cat error.tmp >>error.out
	exit $E_CORRECT
else
	echo "Wrong answer${descrp}." >>error.out
	cat error.tmp >>error.out
	exit $E_ANSWER
fi

# This should never be reached
exit $E_INTERN
