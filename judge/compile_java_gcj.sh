#!/bin/sh

# Java compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj. Note that
# a static version of libgcj must be available, which is not always
# the case. In addition, it removes some warnings that gcj generates
# by default with static compilation. These warnings have confused
# teams in the past.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

TMPFILE=`mktemp --tmpdir domjudge_gcj_output.XXXXXX` || exit 1

# -Wall:	Report all warnings
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
gcj -Wall -O2 -static-libgcj -pipe --main=Main -o $DEST "$@" > $TMPFILE 2>&1
exitcode=$?
grep -vE 'requires at runtime the shared libraries|libgcj\.a' $TMPFILE
rm -f $TMPFILE

exit $exitcode
