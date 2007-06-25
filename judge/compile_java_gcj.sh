#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj.

SOURCE="$1"
DEST="$2"

# -Wall:	Report all warnings
# -static:	Static link with all libraries
gcj -Wall -O2 -static --main=Main -o $DEST $SOURCE
exit $?
