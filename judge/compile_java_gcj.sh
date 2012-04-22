#!/bin/sh

# Java compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

# -Wall:	Report all warnings
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
gcj -Wall -O2 -static -pipe --main=Main -o $DEST "$@"
exit $?
