#!/bin/sh

# Psacal compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

# -viwn:	Verbose warnings, notes and informational messages
# -02:		Level 2 optimizations (default for speed)
# -Sg:		Support label and goto commands (for those who need it ;-)
# -XS:		Static link with all libraries
fpc -viwn -O2 -Sg -XS -dONLINE_JUDGE -dDOMJUDGE -o$DEST "$MAINSOURCE"
exitcode=$?

# clean created object files:
rm -f $DEST.o

exit $exitcode
