#!/bin/sh

# Simple program for compare_program.sh script: this just runs a diff
# comparison between the program and expected output. Should give same
# results as the default compare.sh script.
#
# Additional options to diff can be passed here, like:
#  -i    Ignore case differences.
#  -b    Ignore changes in the amount of white space.
#  -B    Ignore changes whose lines are all blank.
# and see 'man diff' for more options.

TESTIN="$1"
PROGRAM="$2"
TESTOUT="$3"

diff -a "$PROGRAM" "$TESTOUT"
EXITCODE=$?

# EXITCODE = 1 indicates differences, others errors:
[ $EXITCODE -gt 1 ] && exit 1

exit 0
