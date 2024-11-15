#!/bin/sh

# Script to test (run and compare) submissions with a single testcase
#
# Usage: $0 <testdata.in> <testdata.out> <timelimit> <workdir>
#           <run> <compare> <compare-args>
#
# <testdata.in>     File containing test-input with absolute pathname.
# <testdata.out>    File containing test-output with absolute pathname.
# <timelimit>       Timelimit in seconds, optionally followed by ':' and
#                   the hard limit to kill still running submissions.
# <workdir>         Directory where to execute submission in a chroot-ed
#                   environment. For best security leave it as empty as possible.
#                   Certainly do not place output-files there!
# <run>             Absolute path to run script to use.
# <compare>         Absolute path to compare script to use, optional.
# <compare-args>    Arguments to pass to compare script, optional.
#
# Default run and compare scripts can be configured in the database.
#
# Exit automatically, whenever a simple command fails and trap it:
set -e
trap 'cleanup ; error' EXIT

cleanup ()
{
	# Remove some copied files to save disk space
	if [ "$WORKDIR" ]; then
		rm -f "$WORKDIR/../../dj-bin/runpipe" 2> /dev/null || true

		# Replace testdata by symlinks to reduce disk usage
		if [ -f "$WORKDIR/testdata.in" ]; then
			rm -f "$WORKDIR/testdata.in"
			ln -s "$TESTIN" "$WORKDIR/testdata.in"
		fi
		if [ -f "$WORKDIR/testdata.out" ]; then
			rm -f "$WORKDIR/testdata.out"
			ln -s "$TESTOUT" "$WORKDIR/testdata.out"
		fi

		# Remove access to workdir for next runs
		chmod go= "$WORKDIR"
	fi

	# Copy runguard and program stderr to system output. The display is
	# truncated to normal size in the jury web interface.
	if [ -s runguard.err ]; then
		echo  "********** runguard stderr follows **********" >> system.out
		cat runguard.err >> system.out
	fi
}

cleanexit ()
{
	set +e
	trap - EXIT

	cleanup

	logmsg $LOG_DEBUG "exiting with status '$1'"
	exit $1
}

# Runs command without error trapping and check exitcode
runcheck ()
{
	logmsg $LOG_DEBUG "runcheck: $*"
	set +e
	"$@"
	exitcode=$?
	set -e
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
RUNPIPE="$DJ_BINDIR/runpipe"
PROGRAM="execdir/program"

logmsg $LOG_INFO "starting '$0', PID = $$"

[ $# -ge 4 ] || error "not enough arguments. See script-code for usage."
TESTIN="$1";    shift
TESTOUT="$1";   shift
TIMELIMIT="$1"; shift
WORKDIR="$1";   shift
RUN_SCRIPT="$1";
COMPARE_SCRIPT="$2";
COMPARE_ARGS="$3";
logmsg $LOG_DEBUG "arguments: '$TESTIN' '$TESTOUT' '$TIMELIMIT' '$WORKDIR'"
logmsg $LOG_DEBUG "optionals: '$RUN_SCRIPT' '$COMPARE_SCRIPT' '$COMPARE_ARGS'"

[ -r "$TESTIN"  ] || error "test-input not found: $TESTIN"
[ -r "$TESTOUT" ] || error "test-output not found: $TESTOUT"
if [ ! -d "$WORKDIR" ] || [ ! -w "$WORKDIR" ] || [ ! -x "$WORKDIR" ]; then
	error "Workdir not found or not writable: $WORKDIR"
fi
if [ -z "$COMPARE_SCRIPT" ]; then
	export COMBINED_RUN_COMPARE=1
else
	export COMBINED_RUN_COMPARE=0
fi
[ -x "$WORKDIR/$PROGRAM" ] || error "submission program not found or not executable: '$WORKDIR/$PROGRAM'"
[ -x "$RUN_SCRIPT" ] || error "run script not found or not executable: $RUN_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"
if [ ! -x "$COMPARE_SCRIPT" ] && [ $COMBINED_RUN_COMPARE -eq 0 ]; then
	error "compare script not found or not executable: $COMPARE_SCRIPT"
fi

cd "$WORKDIR"

# Get the last two directory entries of $PWD
PREFIX="/$(basename $(realpath "$PWD/.."))/$(basename "$PWD")"

# Make testing/execute dir accessible for RUNUSER:
chmod a+x "$WORKDIR" "$WORKDIR/execdir"

# Create files which are expected to exist:
touch system.out                 # Judging system output (info/debug/error)
touch program.out program.err    # Program output and stderr (for extra information)
touch program.meta runguard.err  # Metadata and runguard stderr
touch compare.meta compare.err   # Compare runguard metadata and stderr

logmsg $LOG_INFO "setting up testing (chroot) environment"

# Copy the testdata input
cp "$TESTIN" "$WORKDIR/testdata.in"

# shellcheck disable=SC2174
mkdir -p -m 0711 ../../bin ../../dj-bin ../../dev
# copy a support program for interactive problems:
cp -pL "$RUNPIPE" ../../dj-bin/runpipe
chmod a+rx        ../../dj-bin/runpipe

# If we need to create a writable temp directory, do so
if [ "$CREATE_WRITABLE_TEMP_DIR" ]; then
	export TMPDIR="$PREFIX/write_tmp"
	# shellcheck disable=SC2174
	mkdir -m 777 -p "$WORKDIR/write_tmp"
fi

# Run the solution program (within a restricted environment):
logmsg $LOG_INFO "running program"

RUNARGS="testdata.in program.out"
if [ $COMBINED_RUN_COMPARE -eq 1 ]; then
	# A combined run and compare script may now already need the
	# feedback directory, and perhaps access to the test answers (but
	# only the original that lives outside the chroot).
	mkdir -p feedback
	RUNARGS="$RUNARGS $TESTOUT compare.meta feedback"
fi

exitcode=0
# To suppress false positive of FILELIMIT misspelling of TIMELIMIT:
# shellcheck disable=SC2153
runcheck "$RUN_SCRIPT" $RUNARGS \
	$GAINROOT "$RUNGUARD" ${DEBUG:+-v -V "DEBUG=$DEBUG"} ${TMPDIR:+ -V "TMPDIR=$TMPDIR"} $CPUSET_OPT \
	-r "$PWD/../.." \
	--nproc=$PROCLIMIT \
	--no-core --streamsize=$FILELIMIT \
	--user="$RUNUSER" --group="$RUNGROUP" \
	--walltime=$TIMELIMIT --cputime=$TIMELIMIT \
	--memsize=$MEMLIMIT --filesize=$FILELIMIT \
	--stderr=program.err --outmeta=program.meta -- \
	"$PREFIX/$PROGRAM" 2>runguard.err

if [ "$CREATE_WRITABLE_TEMP_DIR" ]; then
	# Revoke access to the TMPDIR as security measure
	chown -R "$(id -un):" "$TMPDIR"
	chmod -R go= "$TMPDIR"
fi

if [ $COMBINED_RUN_COMPARE -eq 0 ]; then
	# We first compare the output, so that even if the submission gets a
	# timelimit exceeded or runtime error verdict later, the jury can
	# still view the diff with what the submission produced.
	logmsg $LOG_INFO "comparing output"

	# Copy testdata output, only after program has run
	cp "$TESTOUT" "$WORKDIR/testdata.out"

	logmsg $LOG_DEBUG "starting compare script '$COMPARE_SCRIPT'"

	exitcode=0
	# Create dir for feedback files and make it writable for $RUNUSER
	mkdir -p feedback
	chmod -R a+w feedback

	runcheck $GAINROOT "$RUNGUARD" ${DEBUG:+-v} $CPUSET_OPT -u "$RUNUSER" -g "$RUNGROUP" \
		-m $SCRIPTMEMLIMIT -t $SCRIPTTIMELIMIT --no-core \
		-f $SCRIPTFILELIMIT -s $SCRIPTFILELIMIT -M compare.meta -- \
		"$COMPARE_SCRIPT" testdata.in testdata.out feedback/ $COMPARE_ARGS < program.out \
				  >compare.tmp 2>&1
fi

# Make sure that all feedback files are owned by the current
# user/group, so that we can append content.
$GAINROOT chown -R "$(id -un):" "$WORKDIR/feedback"
chmod -R go-w feedback

# Make sure that feedback file exists, since we assume this later.
if [ ! -f feedback/judgemessage.txt ]; then
	touch feedback/judgemessage.txt
fi

# Append output validator error messages
# TODO: display extra
if [ -s feedback/judgeerror.txt ]; then
	printf "\\n---------- output validator (error) messages ----------\\n" >> feedback/judgemessage.txt
	cat feedback/judgeerror.txt >> feedback/judgemessage.txt
fi

logmsg $LOG_DEBUG "checking compare script exit-status: $exitcode"
if grep '^time-result: .*timelimit' compare.meta >/dev/null 2>&1 ; then
	logmsg $LOG_ERR "Comparing aborted after $SCRIPTTIMELIMIT seconds, compare script output:\\n$(cat compare.tmp)"
	cleanexit ${E_COMPARE_ERROR:-1}
fi
# Append output validator stdin/stderr - display extra?
if [ -s compare.tmp ]; then
	printf "\\n---------- output validator stdout/stderr messages ----------\\n" >> feedback/judgemessage.txt
	cat compare.tmp >> feedback/judgemessage.txt
fi
if [ $exitcode -ne 42 ] && [ $exitcode -ne 43 ]; then
	logmsg $LOG_ERR "Comparing failed with exitcode $exitcode, compare script output:\\n$(cat feedback/judgemessage.txt)"
	cleanexit ${E_COMPARE_ERROR:-1}
fi

# Check for errors from running the program:
if [ ! -r program.meta ]; then
	error "'program.meta' not readable"
fi
logmsg $LOG_DEBUG "checking program run exit-status"
# There's no bash YAML parser, and the format is rigid enough that we
# can parse it with grep here.
timeused=$(        grep '^time-used: '    program.meta | sed 's/time-used: //')
program_cputime=$( grep '^cpu-time: '     program.meta | sed 's/cpu-time: //')
program_walltime=$(grep '^wall-time: '    program.meta | sed 's/wall-time: //')
program_exit=$(    grep '^exitcode: '     program.meta | sed 's/exitcode: //')
program_stdout=$(  grep '^stdout-bytes: ' program.meta | sed 's/stdout-bytes: //')
program_stderr=$(  grep '^stderr-bytes: ' program.meta | sed 's/stderr-bytes: //')
memory_bytes=$(    grep '^memory-bytes: ' program.meta | sed 's/memory-bytes: //')
resourceinfo="\
runtime: ${program_cputime}s cpu, ${program_walltime}s wall
memory used: ${memory_bytes} bytes"

if [ $COMBINED_RUN_COMPARE -eq 1 ] && grep '^validator-exited-first: true' compare.meta > /dev/null 2>&1 && grep '^exitcode: 43' compare.meta > /dev/null 2>&1 ; then
	# For interactive problems with combined run/compare scripts, a
	# WA may override TLE and RTE.
	# FIXME: Maybe we are interested in when what program exited. If so, we
	# can write this to compare.meta
	if grep '^time-result: .*timelimit' program.meta >/dev/null 2>&1 ; then
		echo "Timelimit exceeded, but validator exited first with WA." >>system.out
	elif [ "$program_exit" != "0" ]; then
		echo "Non-zero exitcode $program_exit, but validator exited first with WA." >>system.out
	fi
	echo "$resourceinfo" >>system.out
	cleanexit ${E_WRONG_ANSWER:-1}
fi

if grep '^time-result: .*timelimit' program.meta >/dev/null 2>&1 ; then
	echo "Timelimit exceeded." >>system.out
	echo "$resourceinfo" >>system.out
	cleanexit ${E_TIMELIMIT:-1}
fi
if [ "$program_exit" != "0" ]; then
	echo "Non-zero exitcode $program_exit" >>system.out
	echo "$resourceinfo" >>system.out
	cleanexit ${E_RUN_ERROR:-1}
fi

if grep -E '^output-truncated: ([a-z]+,)*stdout(,[a-z]+)*' program.meta >/dev/null 2>&1 ; then
	echo "Output limit exceeded: $program_stdout > $((FILELIMIT*1024))" >>system.out
	echo "$resourceinfo" >>system.out
	cleanexit ${E_OUTPUT_LIMIT:-1}
fi

if [ $exitcode -eq 42 ]; then
	echo "Correct!" >>system.out
	echo "$resourceinfo" >>system.out
	cleanexit ${E_CORRECT:-1}
elif [ $exitcode -eq 43 ]; then
	# Special case detect no-output:
	if [ ! -s program.out ] && [ $COMBINED_RUN_COMPARE -eq 0 ];  then
		echo "Program produced no output." >>system.out
		echo "$resourceinfo" >>system.out
		cleanexit ${E_NO_OUTPUT:-1}
	fi
	echo "Wrong answer." >>system.out
	echo "$resourceinfo" >>system.out
	cleanexit ${E_WRONG_ANSWER:-1}
fi

# This should never be reached
exit ${E_INTERNAL_ERROR:-1}
