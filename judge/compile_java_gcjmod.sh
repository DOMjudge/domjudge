#!/bin/sh

# Java compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj. In
# addition, it removes some warnings that gcj generates by default
# with static compilation. These warnings have confused teams in the
# past.

SOURCE="$1"
DEST="$2"

TMPFILE=`mktemp /tmp/domjudge_gcj_output.XXXXXX` || exit 1

# -Wall:	Report all warnings
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
gcj -Wall -O2 -static -pipe --main=Main -o $DEST $SOURCE > $TMPFILE 2>&1
exitcode=$?
grep -vE 'requires at runtime the shared libraries|libgcj\.a' $TMPFILE
rm -f $TMPFILE

exit $exitcode
