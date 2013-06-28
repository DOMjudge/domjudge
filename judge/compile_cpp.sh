#!/bin/sh

# C++ compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

# -g:		Enable debug symbols
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
g++ -g -O2 -std=gnu++0x -static -pipe -DONLINE_JUDGE -DDOMJUDGE -o $DEST "$@"
exit $?
