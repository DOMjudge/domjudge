#!/bin/bash
# $Id$

# Script to compile and run solutions.
# Written by Jaap Eldering, April 2004
#
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
# This script supports languages, by calling separate compile and run scripts
# depending on <lang>, namely 'compile_<lang>.sh' and 'run_<lang>.sh'.
# Syntax for these scripts should be:
#
# compile_<lang>.sh  <source> <dest>
# run_<lang>.sh      <dest> <testdata.in> <output>
#
# Where both scripts may optionally modify <dest>, but then both scripts
# must do so the same way (e.g. for Java: '<dest>.class').
#

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
		logmsg $LOG_ERROR "error: $@"
	else
		logmsg $LOG_ERROR "unexpected error, aborting!"
	fi

	exit $E_INTERN
}

# Global configuration
source `dirname $0`/../etc/config.sh

# Exit/error codes:
E_CORRECT=0
E_COMPILE=1
E_TIMELIMIT=2
E_RUNERROR=3
E_OUTPUT=4
E_ANSWER=5
E_INTERN=127 # Internal script error

# Logging:
LOGFILE="$LOGDIR/judge.$HOSTNAME.log"
LOGLEVEL=$LOG_DEBUG
PROGNAME=`basename $0`

# Set this for extra verbosity:
#VERBOSE=$LOG_DEBUG
if [ "$VERBOSE" ]; then
	export VERBOSE
else
	export VERBOSE=$LOG_ERROR
fi

# Location of scripts/programs:
RUNSCRIPTDIR=$SYSTEM_ROOT/testsol
BASHSTATIC=$SYSTEM_ROOT/runprogs/bash-static
RUNGUARD=$SYSTEM_ROOT/runprogs/runguard

logmsg $LOG_NOTICE "starting '$0', PID = $$"

[ $# -eq 6 ] || error "wrong number of arguments. see script-code for usage."
SOURCE="$1";    shift
LANG="$1";      shift
TESTIN="$1";    shift
TESTOUT="$1";   shift
TIMELIMIT="$1"; shift
TMPDIR="$1";    shift
logmsg $LOG_INFO "arguments: $SOURCE $LANG $TESTIN $TESTOUT $TIMELIMIT $TMPDIR"

[ -r $SOURCE ]  || error "solution not found: $SOURCE";
[ -r $TESTIN ]  || error "test-input not found: $TESTIN";
[ -r $TESTOUT ] || error "test-ouput not found: $TESTOUT";
[ -d $TMPDIR -a -w $TMPDIR -a -x $TMPDIR ] || \
	error "Tempdir not found or not writable: $TMPDIR"

logmsg $LOG_NOTICE "setting resource limits"
ulimit -HS -c 0     # Do not write core-dumps
ulimit -HS -f 65536 # Maximum filesize in KB

logmsg $LOG_NOTICE "creating input/output files"
cp $TESTIN $TMPDIR
cp $SOURCE $TMPDIR
TESTIN=`basename $TESTIN`
SOURCE=`basename $SOURCE`
DEST="${SOURCE%.*}"

OLDDIR=$PWD
cd $TMPDIR

# Create files, which are expected to exist:
touch compile.time      # Compiler runtime
touch compile.{out,tmp} # Compiler output
touch error.{out,tmp}   # Error output while running program
touch diff.{out,tmp}    # Compare output

# program.{out,time,exit} are created by processes running as RUNUSER and
# should NOT be created here, or "Permission denied" will result when writing.

logmsg $LOG_NOTICE "starting compile"

if [ `cat $SOURCE | wc -c` -gt $((SOURCESIZE*1024)) ]; then
	echo "Source-code is larger than $SOURCESIZE KB." >>compile.out
	exit $E_COMPILE
fi

( $RUNGUARD -t $COMPILETIME -o compile.time \
	$RUNSCRIPTDIR/compile_$LANG.sh $SOURCE $DEST
) &>compile.tmp
exitcode=$?

# Check for errors during compile:
if grep 'timelimit reached: aborting command' compile.tmp &>/dev/null; then
	echo "Compiling aborted after $COMPILETIME seconds." >>compile.out
	rm compile.tmp
	exit $E_COMPILE
fi
if [ $exitcode -ne 0 ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >>compile.out
	cat compile.tmp >>compile.out
	rm compile.tmp
	exit $E_COMPILE
fi
mv compile.tmp compile.out

logmsg $LOG_NOTICE "setting up chroot-ed environment"

mkdir bin dev proc
# Copy the run-script and a statically compiled bash-shell:
cp -p $RUNSCRIPTDIR/run.sh .
cp -p $RUNSCRIPTDIR/run_$LANG.sh .
cp -p $BASHSTATIC ./bin/bash
# Mount (bind) the proc filesystem (needed by Java):
sudo mount -n -t proc --bind /proc proc
# Make a fifo link to the real /dev/null:
mkfifo -m a+rw ./dev/null
cat < ./dev/null >/dev/null &
CATPID=$!
disown $CATPID
# Make directory (sticky) writable for program output:
chmod a+rwxt .

logmsg $LOG_NOTICE "running program"

( $RUNGUARD -r $PWD -u $RUNUSER -t $TIMELIMIT -o program.time \
	/run.sh $LANG /$DEST $TESTIN program.out error.out program.exit $MEMLIMIT \
) &>error.tmp
exitcode=$?
cat error.out >>error.tmp

sudo umount $PWD/proc
# Check for still running processes (first wait for all exiting processes):
sleep 1
if ps -u $RUNUSER &>/dev/null; then
	error "found processes still running"
fi

# Check for errors from running the program: 
if grep  'timelimit reached: aborting command' error.tmp &>/dev/null; then
	echo "Timelimit exceeded." >>error.out
	cat error.tmp >>error.out
	rm error.tmp
	exit $E_TIMELIMIT
fi
if [ ! -r program.exit ]; then
	mv error.tmp error.out
	error "'program.exit' not readable"
fi
if [ `cat program.exit` != "0" ]; then
	echo "Non-zero exitcode `cat program.exit`" >>error.out
	cat error.tmp >>error.out
	rm error.tmp
	exit $E_RUNERROR
fi
if [ $exitcode -ne 0 ]; then
	mv error.tmp error.out
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
cd $OLDDIR
cp $TESTOUT $TMPDIR
TESTOUT=`basename $TESTOUT`
cd $TMPDIR

if [ ! -s program.out ]; then
	echo "Program produced no output." >>error.out
	cat error.tmp >>error.out
	rm error.tmp
	exit $E_OUTPUT
fi

( diff program.out $TESTOUT >diff.out ) 2>diff.tmp
exitcode=$?

if [ $exitcode -eq 1 ]; then
	echo "Wrong answer." >>error.out
	cat error.tmp >>error.out
	rm error.tmp
	exit $E_ANSWER
fi
if [ $exitcode -ne 0 ]; then
	mv error.tmp error.out
	error "diff: `cat diff.tmp`";
fi
rm diff.tmp

echo "Correct!" >>error.out
cat error.tmp >>error.out
rm error.tmp
exit $E_CORRECT
