#!/bin/bash

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

SOURCE="$1"
DEST="$2"

TMPFILE=`mktemp /tmp/domjudge_gcj_output.XXXXXX`

# -Wall:	Report all warnings
# -static:	Static link with all libraries
gcj -Wall -O2 -static --main=Main -o $DEST $SOURCE &> $TMPFILE
exitcode=$?
grep -vE 'requires at runtime the shared libraries|libgcj\.a' $TMPFILE
rm -f $TMPFILE

exit $exitcode
