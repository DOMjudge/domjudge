#!/bin/sh

# C compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

# -Wall:	Report all warnings
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
# -lm:		Link with math-library (has to be last argument!)
gcc -Wall -O2 -static -pipe -o $DEST "$@" -lm
exit $?
