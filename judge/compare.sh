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

FIRSTDIFF=""
LINEMAXLEN=10
LINEDIGITS=6

OLD_IFS="$IFS"
IFS='
'

exec 3<$PROGRAM
exec 4<$TESTDATA

LINE=0
while true ; do
	((LINE++))
	
	if ! read PROGLINE <&3 ; then
		if read TESTLINE <&4 ; then
			if [ -z "$FIRSTDIFF" ]; then FIRSTDIFF=$LINE ; fi
		else
			break
		fi
	else
		if ! read TESTLINE <&4 ; then
			if [ -z "$FIRSTDIFF" ]; then FIRSTDIFF=$LINE ; fi
		fi
	fi

	if [ "$PROGLINE" != "$TESTLINE" ]; then
		if [ -z "$FIRSTDIFF" ]; then FIRSTDIFF=$LINE ; fi
	fi

	if [ ${#PROGLINE} -gt $LINEMAXLEN ]; then LINEMAXLEN=${#PROGLINE} ; fi
	if [ ${#TESTLINE} -gt $LINEMAXLEN ]; then LINEMAXLEN=${#TESTLINE} ; fi
done

# Add 2 chars to line length to account for surrounding qoutes
((LINEMAXLEN+=2))

exec 3<&-
exec 4<&-

echo "### DIFFERENCES FROM LINE $FIRSTDIFF ###" >$DIFFOUT

exec 3<$PROGRAM
exec 4<$TESTDATA

LINE=0
SEPCHAR='?'
while true ; do
	((LINE++))
	SEPCHAR='='
	if ! read PROGLINE <&3 ; then
		PROGLINE=""
		if read TESTLINE <&4 ; then
			SEPCHAR='>'
		else
			break
		fi
	else
		if ! read TESTLINE <&4 ; then
			TESTLINE=""
			SEPCHAR='<'
		fi
	fi
	
	if [ "$PROGLINE" != "$TESTLINE" -a "$SEPCHAR" = '=' ]; then
		SEPCHAR='!'
	fi

	printf "%-${LINEDIGITS}d %-${LINEMAXLEN}s $SEPCHAR %-${LINEMAXLEN}s\n" $LINE "'$PROGLINE'" "'$TESTLINE'"

done >>$DIFFOUT

exit 0
