#!/bin/bash
# $Id$

# Compare wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

# Usage: $0 <program> <testdata> <diffout>
#
# <program>   File containing solution output.
# <testdata>  File containing correct output.
# <diffout>   File to write differences to.
#
# Exits successfully except when an internal error occurs.
# Program output is considered correct when diffout is empty.

PROGRAM="$1"
TESTDATA="$2"
DIFFOUT="$3"

# Test an exact match between program output and testdata output:
diff -u0 $PROGRAM $TESTDATA >$DIFFOUT

# Exit with failure, when diff reports internal errors:
# Exitcode 1 means that differences were found!
if [ $? -ge 2 ]; then
	exit 1
fi

# Format the diff output to a reasonably readable format:
TMPFILE=`tempfile -d "$PWD" -p diff -s .tmp`
[ -r $TMPFILE ] || exit 1
cp -a $DIFFOUT $TMPFILE

cat $TMPFILE | \
	grep -vE '^(\-\-\-|\+\+\+)' | \
	sed -e 's/^-/PROG: /g;s/^+/TEST: /g;s/^\@\@ -\([0-9]*\).*/### LINE \1 ###/' \
	>$DIFFOUT || exit 1

rm -f $TMPFILE

exit 0
