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

### diff met optie '-b' voor whitespace, voor DS-practicum ###
diff -b $PROGRAM $TESTDATA > $DIFFOUT || exit 1

exit 0
