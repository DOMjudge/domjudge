#!/bin/bash
# $Id$

# Compare wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

# Usage: $0 <program> <testdata> <diffout>
#
# <program>   File containing solution output.
# <testdata>  File containing correct output.
# <diffout>   File to write differences to.
#
# Exits successfully except when an internal error occurs.
# Program output is considered correct when diffout is empty.

PROGRAM="$1"
TESTDATA="$2"
DIFFOUT="$3"

# Width of the diff output (minimum 50):
OUTPUTWIDTH=80
OUTPUTHALF=$((OUTPUTWIDTH/2 - 2))

# Test an exact match between program output and testdata output:
diff -U 0 $PROGRAM $TESTDATA >$DIFFOUT

# Exit with failure, when diff reports internal errors:
# Exitcode 1 means that differences were found!
if [ $? -ge 2 ]; then
	exit 1
fi

# Exit when no differences found:
if [ ! -s $DIFFOUT ]; then
	exit 0
fi

exec 3<$PROGRAM
exec 4<$TESTDATA

LINE=0
DIFFBLOCK=0
while true ; do
	if ! read PROGLINE <&3 ; then
		if read TESTLINE <&4 ; then
			echo "### MORE TESTDATA OUTPUT:"
			echo "$TESTLINE"
			cat <&4
		fi
		break
	fi
	if ! read TESTLINE <&4 ; then
		echo "### MORE PROGRAM OUTPUT:"
		echo "$PROGLINE"
		cat <&3
		break
	fi

	((LINE++))
	if [ "$PROGLINE" != "$TESTLINE" ]; then
		if [ $DIFFBLOCK -eq 0 ]; then
			DIFFBLOCK=1
			printf "### LINE %-6d%-$((OUTPUTHALF-23))s PROGRAM | TESTDATA\n" "$LINE" " "
		fi
		printf "%-${OUTPUTHALF}s | %-${OUTPUTHALF}s\n" "$PROGLINE" "$TESTLINE"
	else
		DIFFBLOCK=0
	fi
done >$DIFFOUT

exec 3<&-
exec 4<&-

exit 0
