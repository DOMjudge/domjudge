#!/bin/bash
# $Id$

# Run wrapper-script for 'test_solution.sh'.
# See that script for more info.

# Usage: $0 <lang> <prog> <testin> <output> <error> <exitfile>
#           <memlimit> <filelimit> <proclimit>
#
# <lang>      Language of the compiled program.
# <prog>      Executable of the program to be run.
# <testin>    File containing test-input.
# <output>    File where to write solution output.
# <error>     File where to write error messages.
# <exitfile>  File where to write solution exitcode.
# <memlimit>  Maximum total memory usage in KB.
# <filelimit> Maximum filesize in KB.
# <proclimit> Maximum total no. processes (including this shell)

LANG="$1";      shift
PROG="$1";      shift
TESTIN="$1";    shift
OUTPUT="$1";    shift
ERROR="$1";     shift
EXITFILE="$1";  shift
MEMLIMIT="$1";  shift
FILELIMIT="$1"; shift
PROCLIMIT="$1"; shift

# Set some resource limits (for restricting the running solution)
ulimit -c 0           # max. size of coredump files in KB
ulimit -v $MEMLIMIT   # max. total memory usage in KB
ulimit -f $FILELIMIT  # max. size of created files in KB
ulimit -u $PROCLIMIT  # max. no. processes

# Run the program while redirecting input, output and stderr
$PROG <$TESTIN >$OUTPUT 2>$ERROR
exitcode=$?

echo -n "$exitcode" >$EXITFILE

exit $exitcode
