#!/bin/bash
# $Id$

# Solution run wrapper-script for 'test_solution.sh'.
# Written by Jaap Eldering, May 2004
#
# Usage: $0 <lang> <prog> <testin> <output> <error> <memlim>
#
# <lang>      Language of the compiled program.
# <testin>    File containing test-input.
# <output>    File where to write solution output.
# <error>     File where to write error messages.
# <memlimit>  Maximum total memory usage in KB.
#
# See 'test_solution.sh' script for more info.
#

LANG="$1";     shift
PROG="$1";     shift
TESTIN="$1";   shift
OUTPUT="$1";   shift
ERROR="$1";    shift
MEMLIMIT="$1"; shift

# Create the program output file, so that it always exists
echo -n >$OUTPUT

# Set some resource limits (for restricting the running solution)
ulimit -c 0         # max. size of coredump files in KB
ulimit -f 65536     # max. size of created files in KB
ulimit -v $MEMLIMIT # max. total memory in KB
ulimit -u 8         # max. processes

# `source' the specific language run-script, which executes the solution
source /run_$LANG.sh

exit $exitcode
