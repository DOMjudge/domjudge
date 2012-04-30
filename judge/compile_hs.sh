#!/bin/sh

# Haskell compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

# -Wall:	Report all warnings
# -O:		Optimize
# -static:	Static link Haskell libraries
# -optl-static:	Pass '-static' option to the linker
# -optl-pthread: Pass '-pthread' option to the linker (see Debian bug #593402)
ghc -Wall -Wwarn -O -static -optl-static -optl-pthread -o $DEST "$@"
exitcode=$?

# clean created files:
rm -f $DEST.o Main.hi

exit $exitcode
