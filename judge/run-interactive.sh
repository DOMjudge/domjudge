#!/bin/sh

# Run wrapper-script to be called from 'testcase_run.sh'.
#
# This script is meant to simplify writing interactive problems where the
# contestants' solution bi-directionally communicates with a jury program, e.g.
# while playing a two player game.
#
# Usage: $0 <testin> <progout> <testout> <metafile> <feedbackdir> <program>...
#
# <testin>      File containing test-input.
# <testout>     File containing test-output.
# <progout>     File where to write solution output. Note: this is unused.
# <feedbackdir> Directory to write jury feedback files to.
# <program>     Command and options of the program to be run.

# A jury-written program called 'runjury' should be available; this program
# will normally be compiled by the build script in the validator directory.
# This program should communicate with the contestants' program to provide
# input and read output via stdin/stdout. This wrapper script handles the setup
# of bi-directional pipes. The jury program should accept the following calling
# syntax:
#
#    runjury <testin> <testout> <feedbackdir> < <output of the program>
#
# The jury program should exit with exitcode 42 if the submissions is accepted,
# 43 otherwise.

TESTIN="$1";  shift
PROGOUT="$1"; shift
TESTOUT="$1"; shift
META="$1"; shift
FEEDBACK="$1"; shift

MYDIR="$(dirname $0)"

# Run the program while redirecting its stdin/stdout to 'runjury' via
# 'runpipe'. Note that "$@" expands to separate, quoted arguments.
exec ../dj-bin/runpipe ${DEBUG:+-v} -M "$META" -o "$PROGOUT" "$MYDIR/runjury" "$TESTIN" "$TESTOUT" "$FEEDBACK" = "$@"
