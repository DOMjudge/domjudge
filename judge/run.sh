#!/bin/bash
# $Id$

# Run wrapper-script for 'test_solution.sh'.
# See that script for more info.

# Usage: $0 <lang> <prog> <testin> <output> <error> <exitfile> <memlim>
#
# <lang>      Language of the compiled program.
# <prog>      Executable of the program to be run.
# <testin>    File containing test-input.
# <output>    File where to write solution output.
# <error>     File where to write error messages.
# <exitfile>  File where to write solution exitcode.
# <memlimit>  Maximum total memory usage in KB.

LANG="$1";     shift
PROG="$1";     shift
TESTIN="$1";   shift
OUTPUT="$1";   shift
ERROR="$1";    shift
EXITFILE="$1"; shift
MEMLIMIT="$1"; shift

# Create the program output file, so that it always exists
echo -n >$OUTPUT
echo -n >$EXITFILE

# Set some resource limits (for restricting the running solution)
ulimit -c 0         # max. size of coredump files in KB
ulimit -f 65536     # max. size of created files in KB
ulimit -v $MEMLIMIT # max. total memory in KB
ulimit -u 8         # max. processes

# `source' the specific language run-script, which executes the solution
source /run_$LANG.sh

echo -n "$exitcode" >$EXITFILE

exit $exitcode
