#!/bin/sh

# Script to test (run and compare) submissions with a single testcase
#
# Usage: $0 <testdata.in> <testdata.out> <timelimit> <workdir>
#           [<special-run> [<special-compare>]]
#
# <testdata.in>     File containing test-input with absolute pathname.
# <testdata.out>    File containing test-output with absolute pathname.
# <timelimit>       Timelimit in seconds, optionally followed by ':' and
#                   the hard limit to kill still running submissions.
# <workdir>         Directory where to execute submission in a chroot-ed
#                   environment. For best security leave it as empty as possible.
#                   Certainly do not place output-files there!
# <special-run>     Extension name of specialized run or compare script to use.
# <special-compare> Specify empty string for <special-run> if only
#                   <special-compare> is to be used. The script
#                   'run_<special-run>' or 'compare_<special-compare>'
#                   will be called if argument is non-empty.
#
# For running the solution a script 'run' is called (default). For
# usage of 'run' see that script. Likewise, for comparing results, a
# program 'compare' is called by default.
#
# Exit automatically, whenever a simple command fails and trap it:
set -e
trap 'cleanup ; error' EXIT

cleanup ()
{
	# Remove some copied files to save disk space
	if [ "$WORKDIR" ]; then
		rm -f "$WORKDIR/../dev/null" "$WORKDIR/../bin/sh" "$WORKDIR/../bin/runpipe"

		# Replace testdata by symlinks to reduce disk usage
		if [ -f "$WORKDIR/testdata.in" ]; then
			rm -f "$WORKDIR/testdata.in"
			ln -s "$TESTIN" "$WORKDIR/testdata.in"
		fi
		if [ -f "$WORKDIR/testdata.out" ]; then
			rm -f "$WORKDIR/testdata.out"
			ln -s "$TESTOUT" "$WORKDIR/testdata.out"
		fi
	fi
}

cleanexit ()
{
	set +e
	trap - EXIT

	cleanup

	logmsg $LOG_DEBUG "exiting"
	exit $1
}

# Runs command without error trapping and check exitcode
runcheck ()
{
	logmsg $LOG_DEBUG "runcheck: $@"
	set +e
	$@
	exitcode=$?
	set -e
}

# Error and logging functions
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
	esac
done
# Shift any of the arguments out of the way
shift $((OPTIND-1))
[ "$1" = "--" ] && shift

if [ -n "$CPUSET" ]; then
	CPUSET_OPT="-P $CPUSET"
	LOGFILE="$DJ_LOGDIR/judge.`hostname | cut -d . -f 1`-$CPUSET.log"
else
	LOGFILE="$DJ_LOGDIR/judge.`hostname | cut -d . -f 1`.log"
fi

# Logging:
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
STATICSHELL="$DJ_LIBJUDGEDIR/sh-static"
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
SPECIALRUN="$1";
SPECIALCOMPARE="$2";
logmsg $LOG_DEBUG "arguments: '$TESTIN' '$TESTOUT' '$TIMELIMIT' '$WORKDIR'"
logmsg $LOG_DEBUG "optionals: '$SPECIALRUN' '$SPECIALCOMPARE'"

COMPARE_SCRIPT="$SCRIPTDIR/compare${SPECIALCOMPARE:+_$SPECIALCOMPARE}"
RUN_SCRIPT="$SCRIPTDIR/run${SPECIALRUN:+_$SPECIALRUN}"
if [ -n "$SPECIALRUN" ]; then
	RUN_JURYPROG="$SCRIPTDIR/runjury_${SPECIALRUN}"
fi

[ -r "$TESTIN"  ] || error "test-input not found: $TESTIN"
[ -r "$TESTOUT" ] || error "test-output not found: $TESTOUT"
[ -d "$WORKDIR" -a -w "$WORKDIR" -a -x "$WORKDIR" ] || \
	error "Workdir not found or not writable: $WORKDIR"
[ -x "$WORKDIR/$PROGRAM" ] || error "submission program not found or not executable"
[ -x "$COMPARE_SCRIPT" ] || error "compare script not found or not executable: $COMPARE_SCRIPT"
[ -x "$RUN_SCRIPT" ] || error "run script not found or not executable: $RUN_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

cd "$WORKDIR"

# Check whether we're going to run in a chroot environment:
if [ -z "$USE_CHROOT" ] || [ "$USE_CHROOT" -eq 0 ]; then
# unset to allow shell default parameter substitution on USE_CHROOT:
	unset USE_CHROOT
	PREFIX=$PWD
else
	PREFIX="/`basename $PWD`"
fi

# Make testing/execute dir accessible for RUNUSER:
chmod a+x "$WORKDIR" "$WORKDIR/execdir"

# Create files which are expected to exist:
touch error.out                  # Error output
touch compare.out                # Compare output
touch result.out                 # Result of comparison
touch program.out program.err    # Program output and stderr (for extra information)
touch program.time program.exit  # Program runtime and exitcode

logmsg $LOG_INFO "setting up testing (chroot) environment"

# Copy the testdata input
cp "$TESTIN" "$WORKDIR/testdata.in"

mkdir -p -m 0711 ../bin ../dev
# Copy the run-script and a statically compiled shell:
cp -p  "$RUN_SCRIPT"  ./run
cp -pL "$STATICSHELL" ../bin/sh
chmod a+rx run ../bin/sh
# If using a custom run script, copy additional support programs
# if required:
if [ -n "$SPECIALRUN" -a -f "$RUN_JURYPROG" ]; then
	cp -p "$RUN_JURYPROG" ./runjury
	cp -pL "$RUNPIPE"     ../bin/runpipe
	chmod a+rx runjury ../bin/runpipe
fi

# We copy /dev/null: mknod (and the major/minor device numbers) are
# not portable, while a fifo link has the problem that a cat program
# must be run and killed.
logmsg $LOG_DEBUG "creating /dev/null character-special device"
$GAINROOT cp -pR /dev/null ../dev/null

# Run the solution program (within a restricted environment):
logmsg $LOG_INFO "running program (USE_CHROOT = ${USE_CHROOT:-0})"

runcheck ./run testdata.in program.out \
	$GAINROOT $RUNGUARD ${DEBUG:+-v} $CPUSET_OPT \
	${USE_CHROOT:+-r "$PWD/.."} -u "$RUNUSER" \
	-t $TIMELIMIT -C $TIMELIMIT -m $MEMLIMIT -f $FILELIMIT -p $PROCLIMIT \
	-c -s $FILELIMIT -e program.err -E program.exit -T program.time -- \
	$PREFIX/$PROGRAM 2>error.tmp

# Check for still running processes:
if ps -u "$RUNUSER" >/dev/null 2>&1 ; then
	error "found processes still running"
fi

# Append (heading/trailing) program stderr to error.tmp:
if [ `wc -l < program.err` -gt 20 ]; then
	echo "*** Program stderr output following (first and last 10 lines) ***" >>error.tmp
	head -n 10 program.err >>error.tmp
	echo "*** <snip> ***"  >>error.tmp
	tail -n 10 program.err >>error.tmp
elif [ -s program.err ]; then
	echo "*** Program stderr output following ***" >>error.tmp
	cat program.err >>error.tmp
fi

# We first compare the output, so that even if the submission gets a
# timelimit exceeded or runtime error verdict later, the jury can
# still view the diff with what the submission produced.
logmsg $LOG_INFO "comparing output"

# Copy testdata output, only after program has run
cp "$TESTOUT" "$WORKDIR/testdata.out"

logmsg $LOG_DEBUG "starting script '$COMPARE_SCRIPT'"

if ! "$COMPARE_SCRIPT" testdata.in program.out testdata.out \
                       result.out compare.out >compare.tmp 2>&1 ; then
	exitcode=$?
	cat error.tmp >>error.out
	error "compare exited with exitcode $exitcode: `cat compare.tmp`";
fi

# Check for errors from running the program:
logmsg $LOG_DEBUG "checking program run exit-status"
if grep  'timelimit exceeded' error.tmp >/dev/null 2>&1 ; then
	echo "Timelimit exceeded." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_TIMELIMIT:--1}
fi
if [ ! -r program.exit ]; then
	cat error.tmp >>error.out
	error "'program.exit' not readable"
fi
# Check that program.exit was written to (no runguard error)
if [ "`cat program.exit`" != "0" ]; then
	echo "Non-zero exitcode `cat program.exit`" >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_RUN_ERROR:--1}
fi

############################################################
### Checks for other runtime errors:                     ###
### Disabled, because these are not consistently         ###
### reported the same way by all different compilers.    ###
############################################################
#if grep  'Floating point exception' error.tmp >/dev/null 2>&1 ; then
#	echo "Floating point exception." >>error.out
#	cleanexit ${E_RUN_ERROR:--1}
#fi
#if grep  'Segmentation fault' error.tmp >/dev/null 2>&1 ; then
#	echo "Segmentation fault." >>tee error.out
#	cleanexit ${E_RUN_ERROR:--1}
#fi
#if grep  'File size limit exceeded' error.tmp >/dev/null 2>&1 ; then
#	echo "File size limit exceeded." >>error.out
#	cat error.tmp >>error.out
#	cleanexit ${E_OUTPUT_LIMIT:--1}
#fi

result=`grep '^result='      result.out | cut -d = -f 2- | tr '[:upper:]' '[:lower:]'`
descrp=`grep '^description=' result.out | cut -d = -f 2-`
descrp="${descrp:+ ($descrp)}"

if [ "$result" = "accepted" ]; then
	echo "Correct${descrp}! Runtime is `cat program.time` seconds." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_CORRECT:--1}
elif [ "$result" = "presentation error" ]; then
	echo "Presentation error${descrp}." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_PRESENTATION_ERROR:--1}
elif [ ! -s program.out ]; then
	echo "Program produced no output." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_NO_OUTPUT:--1}
elif [ "$result" = "wrong answer" ]; then
	echo "Wrong answer${descrp}." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_WRONG_ANSWER:--1}
else
	echo "Unknown result: Wrong answer#${descrp}#${result}#." >>error.out
	cat error.tmp >>error.out
	cleanexit ${E_WRONG_ANSWER:--1}
fi

# This should never be reached
exit ${E_INTERNAL_ERROR:--1}
