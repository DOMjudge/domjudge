#!/bin/bash

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

SOURCE="$1"
DEST="$2"

# -Wall:	Report all warnings
# -static:	Static link with all libraries
gcj -Wall -O2 -static --main=Main -o $DEST $SOURCE
exit $?
