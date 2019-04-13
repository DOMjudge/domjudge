#!/bin/sh

# Script to compile submissions.
#
# Usage: $0 <compile_script> <workdir> <file>...
#
# <compile_script>  Absolute path to compile script.
# <workdir>         Base directory of this judging. Compilation is done in
#                   <workdir>/compile, compiler output is stored in <workdir>.
# <file>...         Source file(s) to be compiled. Files are passed in the
#                   same order as specified during submission. It is up to the
#                   specific compiler script to interpret how to compile this;
#                   the first file should conventionally be interpreted as the
#                   "main" file.
#
# Returns with exit status 0 on success, or 'compiler-error' (see
# EXITCODES in etc/judgehost-static.php), or 'internal-error' if
# defined, else 1.
#
# This script supports languages by calling separate compile scripts.
# These compile scripts should compile the source(s) to a statically linked,
# standalone executable, or create a chroot environment that has
# interpreter/dynamic library support.
#
# Syntax for these compile scripts is:
#
#   <compile_script> <dest> <memlimit> <source file>...
#
# where <dest> is the filename of a resulting executable file that the
# compile script must create. This executable should run the submission
# in some way; compilation is considered failed if <dest> is not created
# or not executable.
# The <memlimit> (in kB, obtained from the environment) is passed to
# the compile script to let interpreted languages (read: Oracle (Sun)
# javac/java) be able to set the internal maximum memory size.
#
# The result is considered a compilation failure if <dest> was not
# created or is not executable or if the script returned a nonzero
# exitcode. If any output line starts with "internal-error: ", this is
# seen as an internal error in the compile script instead.

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error EXIT

cleanexit ()
{
	trap - EXIT

	chmod go= "$WORKDIR/compile"
	logmsg $LOG_DEBUG "exiting, code = '$1'"
	exit $1
}

# Error and logging functions
# shellcheck disable=SC1090
. "$DJ_LIBDIR/lib.error.sh"

CPUSET=""
CPUSET_OPT=""
# Do argument parsing
OPTIND=1 # reset if necessary
while getopts "n:" opt; do
	case $opt in
		n)
			CPUSET="$OPTARG"
			;;
		:)
			echo "Option -$OPTARG requires an argument." >&2
			;;
		*)
			echo "Invalid option specified." >&2
			exit 1
			;;
	esac
done
# Shift any of the arguments out of the way
shift $((OPTIND-1))
[ "$1" = "--" ] && shift

if [ -n "$CPUSET" ]; then
	CPUSET_OPT="-P $CPUSET"
	LOGFILE="$DJ_LOGDIR/judge.$(hostname | cut -d . -f 1)-$CPUSET.log"
else
	LOGFILE="$DJ_LOGDIR/judge.$(hostname | cut -d . -f 1).log"
fi

# Logging:
LOGLEVEL=$LOG_DEBUG
PROGNAME="$(basename "$0")"

# Check for judge backend debugging:
if [ "$DEBUG" ]; then
	export DEBUG
	export VERBOSE=$LOG_DEBUG
	logmsg $LOG_NOTICE "debugging enabled, DEBUG='$DEBUG'"
else
	export VERBOSE=$LOG_ERR
fi

# Location of scripts/programs:
SCRIPTDIR="$DJ_LIBJUDGEDIR"
GAINROOT="sudo -n"
RUNGUARD="$DJ_BINDIR/runguard"

logmsg $LOG_INFO "starting '$0', PID = $$"

[ $# -ge 3 ] || error "not enough arguments. See script-code for usage."
COMPILE_SCRIPT="$1";    shift
WORKDIR="$1"; shift
logmsg $LOG_DEBUG "arguments: '$COMPILE_SCRIPT' '$WORKDIR'"
logmsg $LOG_DEBUG "source file(s): $*"

if [ ! -d "$WORKDIR" ] || [ ! -w "$WORKDIR" ] || [ ! -x "$WORKDIR" ]; then
	error "Workdir not found or not writable: $WORKDIR"
fi
[ -x "$COMPILE_SCRIPT" ] || error "compile script not found or not executable: $COMPILE_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

OLDDIR="$PWD"
cd "$WORKDIR"

# Make compile dir accessible and writable for RUNUSER:
chmod a+rwx "$WORKDIR/compile"

# Create files which are expected to exist: compiler output and runtime
touch compile.out compile.meta

cd "$WORKDIR/compile"

for src in "$@" ; do
	[ -r "$src"  ] || error "source file not found: $src"

	# Make source(s) readable (in case it is interpreted):
	chmod a+r "$src"
done

logmsg $LOG_INFO "starting compile"

if [ ! -z "$ENTRY_POINT" ]; then
	ENVIRONMENT_VARS="-V ENTRY_POINT=$ENTRY_POINT"
fi
if [ -n "$DEBUG" ]; then
	ENVIRONMENT_VARS="$ENVIRONMENT_VARS -V DEBUG=$DEBUG"
fi

# First compile to 'source' then rename to 'program' to avoid problems with
# the compiler writing to different filenames and deleting intermediate files.
exitcode=0
$GAINROOT "$RUNGUARD" ${DEBUG:+-v} $CPUSET_OPT -u "$RUNUSER" -g "$RUNGROUP" \
	-m $SCRIPTMEMLIMIT -t $SCRIPTTIMELIMIT -c -f $SCRIPTFILELIMIT -s $SCRIPTFILELIMIT \
	-M "$WORKDIR/compile.meta" $ENVIRONMENT_VARS -- \
	"$COMPILE_SCRIPT" program "$MEMLIMIT" "$@" >"$WORKDIR/compile.tmp" 2>&1 || \
	exitcode=$?

# Make sure that all files are owned by the current user/group, so
# that we can delete the judging output tree without root access.
# We also remove group RUNGROUP so that this can safely be shared
# across multiple judgedaemons, and remove write permissions.
$GAINROOT chown -R "$(id -un):" "$WORKDIR/compile"
chmod -R go-w "$WORKDIR/compile"

cd "$WORKDIR"

if [ $exitcode -ne 0 ] && [ ! -s compile.meta ]; then
	echo "internal-error: runguard crashed" > compile.meta
	echo "Runguard exited with code $exitcode and 'compile.meta' is empty, it likely crashed." >compile.out
	echo "Compilation output:" >>compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_INTERNAL_ERROR:-1}
fi
if grep -i '^internal-error: ' compile.tmp >/dev/null 2>&1 ; then
	grep -i '^internal-error: ' compile.tmp | sed 's/^internal-error:/\1 compile script:/i' >>compile.meta
	echo "The compile script threw an internal error. Compilation output:" >compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_INTERNAL_ERROR:-1}
fi

# Check if the compile script auto-detected the entry point, and if
# so, store it in the compile.meta for later reuse, e.g. in a replay.
grep '[Dd]etected entry_point: ' compile.tmp | sed 's/^.*etected //' >>compile.meta

logmsg $LOG_DEBUG "checking compilation exit-status"
if grep '^time-result: .*timelimit' compile.meta >/dev/null 2>&1 ; then
	echo "Compiling aborted after $SCRIPTTIMELIMIT seconds, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_COMPILER_ERROR:-1}
fi
if [ $exitcode -ne 0 ]; then
	echo "Compiling failed with exitcode $exitcode, compiler output:" >compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_COMPILER_ERROR:-1}
fi
if [ ! -f compile/program ] || [ ! -x compile/program ]; then
	echo "Compiling failed: no executable was created; compiler output:" >compile.out
	cat compile.tmp >>compile.out
	cleanexit ${E_COMPILER_ERROR:-1}
fi
cat compile.tmp >>compile.out

logmsg $LOG_INFO "Compilation successful"
cleanexit 0
