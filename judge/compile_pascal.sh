#!/bin/bash

# Psacal compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

SOURCE="$1"
DEST="$2"

# -vwnh:	Verbose warnings, notes and hints
# -02:		Level 2 optimizations (default for speed)
# -Sg:		Support label and goto commands (for those who need it ;-)
# -XS:		Static link with all libraries
fpc -vwnh -O2 -Sg -XS -o$DEST $SOURCE
exitcode=$?

# clean created object files:
rm -f $DEST.o

exit $exitcode
