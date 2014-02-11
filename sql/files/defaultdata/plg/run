#!/bin/sh

# Prolog compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

exec swipl --goal=main,halt --stand_alone=true -o $DEST -c $MAINSOURCE

exit 0
