#!/bin/sh

# Script to compile submissions.
#
# Usage: $0 <lang> <workdir>
#
# <lang>            Language ID and extension of the source, see config-file
#                   for details; source file must be located in
#                   <workdir>/compile/source.<lang>
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
# interpreted languages (read: Oracle (Sun) javac/java) be able to set the
# internal maximum memory size.

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error EXIT

cleanexit ()
{
	trap - EXIT

	logmsg $LOG_DEBUG "exiting, code = '$1'"
	exit $1
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

[ $# -ge 2 ] || error "not enough arguments. See script-code for usage."
LANG="$1";    shift
WORKDIR="$1"; shift
logmsg $LOG_DEBUG "arguments: '$LANG' '$WORKDIR'"

COMPILE_SCRIPT="$SCRIPTDIR/compile_$LANG.sh"
SOURCE="$WORKDIR/compile/source.$LANG"

[ -r "$SOURCE"  ] || error "source not found: $SOURCE"
[ -d "$WORKDIR" -a -w "$WORKDIR" -a -x "$WORKDIR" ] || \
	error "Workdir not found or not writable: $WORKDIR"
[ -x "$COMPILE_SCRIPT" ] || error "compile script not found or not executable: $COMPILE_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

OLDDIR="$PWD"
cd "$WORKDIR"

# Create files which are expected to exist: compiler output and runtime
touch compile.out compile.time

# Make source readable (in case it is interpreted):
chmod a+r "$SOURCE"

logmsg $LOG_INFO "starting compile"
cd "$WORKDIR/compile"

# First compile to 'source' then rename to 'program' to avoid problems with
# the compiler writing to different filenames and deleting intermediate files.
exitcode=0
"$RUNGUARD" ${DEBUG:+-v} -t $COMPILETIME -c -f 65536 -T "$WORKDIR/compile.time" -- \
	"$COMPILE_SCRIPT" "`basename $SOURCE`" source "$MEMLIMIT" >"$WORKDIR/compile.tmp" 2>&1 || \
	exitcode=$?
if [ -f source ]; then
    mv -f source program
    chmod a+rx program
fi

cd "$WORKDIR"

logmsg $LOG_DEBUG "checking compilation exit-status"
if grep 'timelimit reached: aborting command' compile.tmp >/dev/null 2>&1 ; then
	echo "Compiling aborted after $COMPILETIME seconds." >compile.out
	cleanexit ${E_COMPILER_ERROR:--1}
fi
if [ $exitcode -ne 0 -o ! -e compile/program ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_COMPILER_ERROR:--1}
fi
cat compile.tmp >>compile.out

logmsg $LOG_INFO "Compilation successful"
cleanexit 0
