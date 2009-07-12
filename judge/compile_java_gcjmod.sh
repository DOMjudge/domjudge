#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj. In
# addition, it removes some warnings that gcj generates by default
# with static compilation. These warnings have confused teams in the
# past.

SOURCE="$1"
DEST="$2"

TMPFILE=`mktemp $DJ_TMPDIR/domjudge_gcj_output.XXXXXX` || exit 1

# -Wall:	Report all warnings
# -static:	Static link with all libraries
gcj -Wall -O2 -static --main=Main -o $DEST $SOURCE > $TMPFILE 2>&1
exitcode=$?
grep -vE 'requires at runtime the shared libraries|libgcj\.a' $TMPFILE
rm -f $TMPFILE

exit $exitcode
