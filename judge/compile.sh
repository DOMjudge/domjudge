#!/usr/bin/env bash

# Script to compile submissions.
#
# $Id$

# Usage: $0 <ext> <lang> <workdir>
#
# <ext>             Extension of the source: <workdir>/compile/source.<ext>
# <lang>            Language of the source, see config-file for details.
# <workdir>         Base directory of this judging. Compilation is done in
#                   <workdir>/compile, compiler output is stored in <workdir>.
#
# This script supports languages by calling separate compile scripts
# depending on <lang>, namely 'compile_<lang>.sh'. These compile
# scripts should compile the source to a statically linked, standalone
# executable, or you should turn off USE_CHROOT, or create a chroot
# environment that has interpreter/dynamic library support.
#
# Syntax for these compile scripts is:
#
#   compile_<lang>.sh <source> <dest> <memlimit>
#
# where <dest> is the same filename as <source> but without extension.
# The <memlimit> (in kB) is passed to the compile script to let
# interpreted languages (read: Sun javac/java) be able to set the
# internal maximum memory size.
#
# This is a bash script because of the traps it uses.

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error ERR
trap cleanexit EXIT

cleanexit ()
{
	trap - EXIT

	logmsg $LOG_DEBUG "exiting"
}

# Runs command without error trapping and check exitcode
runcheck ()
{
	set +e
	trap - ERR
	$@
	exitcode=$?
	set -e
	trap error ERR
}

# Error and logging functions
. "$DJ_LIBDIR/lib.error.sh"

# Logging:
LOGFILE="$DJ_LOGDIR/judge.`hostname | cut -d . -f 1`.log"
LOGLEVEL=$LOG_DEBUG
PROGNAME="`basename $0`"

# Check for judge backend debugging:
if [ "$DEBUG" ]; then
	export VERBOSE=$LOG_DEBUG
	logmsg $LOG_NOTICE "debugging enabled, DEBUG='$DEBUG'"
else
	export VERBOSE=$LOG_ERR
fi

# Location of scripts/programs:
SCRIPTDIR="$DJ_LIBJUDGEDIR"
RUNGUARD="$DJ_BINDIR/runguard"

logmsg $LOG_INFO "starting '$0', PID = $$"

[ $# -ge 3 ] || error "not enough of arguments. see script-code for usage."
EXT="$1";     shift
LANG="$1";    shift
WORKDIR="$1"; shift
logmsg $LOG_DEBUG "arguments: '$SEXT' '$LANG' '$WORKDIR'"

COMPILE_SCRIPT="$SCRIPTDIR/compile_$LANG.sh"
SOURCE="$WORKDIR/compile/source.$EXT"

[ -r "$SOURCE"  ] || error "source not found: $SOURCE"
[ -d "$WORKDIR" -a -w "$WORKDIR" -a -x "$WORKDIR" ] || \
	error "Workdir not found or not writable: $WORKDIR"
[ -x "$COMPILE_SCRIPT" ] || error "compile script not found or not executable: $COMPILE_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

logmsg $LOG_INFO "setting resource limits"
ulimit -HS -c 0     # Do not write core-dumps
ulimit -HS -f 65536 # Maximum filesize in kB

OLDDIR="$PWD"
cd "$WORKDIR"

# Create files which are expected to exist: compiler output and runtime
touch compile.out compile.time

# Make source readable (for if it is interpreted):
chmod a+r "$SOURCE"

logmsg $LOG_INFO "starting compile"
cd "$WORKDIR/compile"

# First compile to 'source' then rename to 'program' to avoid problems with
# the compiler writing to different filenames and deleting intermediate files.
runcheck "$RUNGUARD" ${DEBUG:+-v} -t $COMPILETIME -f $FILELIMIT -o "$WORKDIR/compile.time" -- \
	"$COMPILE_SCRIPT" "`basename $SOURCE`" source "$MEMLIMIT" &>"$WORKDIR/compile.tmp"
if [ -f source ]; then
    mv -f source program
    chmod a+rx program
fi

cd "$WORKDIR"

logmsg $LOG_DEBUG "checking compilation exit-status"
if grep 'timelimit reached: aborting command' compile.tmp &>/dev/null; then
	echo "Compiling aborted after $COMPILETIME seconds." >compile.out
	exit $E_COMPILER_ERROR
fi
if [ $exitcode -ne 0 -o ! -e compile/program ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	exit $E_COMPILER_ERROR
fi
cat compile.tmp >>compile.out

logmsg $LOG_INFO "Compilation successful"
exit 0
