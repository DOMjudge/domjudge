#!/bin/bash
# $Id$

# Compare wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script is written to comply with the ICPC Validator Interface Standard
# as described in http://www.ecs.csus.edu/pc2/doc/valistandard.html.

# Usage: $0 <testdata.in> <program.out> <testdata.out> <result.xml> <diff.out>
#
# <testdata.in>   File containing testdata input.
# <program.out>   File containing the program output.
# <testdata.out>  File containing the correct output.
# <result.xml>    File containing an XML document describing the result.
# <diff.out>      File to write program/correct output differences to.
#
# Exits successfully except when an internal error occurs.
# Program output is considered correct when diffout is empty.

TESTIN="$1"
PROGRAM="$2"
TESTOUT="$3"
RESULT="$4"
DIFFOUT="$5"

function writeresult()
{
    ( cat <<EOF
<?xml version="1.0"?>
<!DOCTYPE result [
  <!ELEMENT result (#PCDATA)>
  <!ATTLIST result outcome CDATA #REQUIRED>
]>
<result outcome="$1">$1</result>
EOF
    ) > "$RESULT"
}

# Test an exact match between program output and testdata output:
DIFFOUTPUT=`diff -U 0 $PROGRAM $TESTOUT`
DIFFEXIT=$?
if [ -z "$DIFFOUTPUT" ]; then
	SOLVED=1
fi

# Exit with failure, when diff reports internal errors:
# Exitcode 1 means that differences were found!
if [ $DIFFEXIT -ge 2 ]; then
	writeresult "Internal error"
	exit 1
fi

# Exit when no differences found:
if [ "$SOLVED" = 1 ]; then
	writeresult "Accepted"
	exit 0
else
	writeresult "Wrong answer"
fi

# Exit when no DIFFOUT given (nothing to do anymore):
if [ -z "$DIFFOUT" ]; then
	exit 0
fi

# Generate a diff output in readable format:

DIRSTDIFF=""
LINEMAXLEN=10
LINEDIGITS=6

OLD_IFS="$IFS"
IFS='
'

exec 3<$PROGRAM
exec 4<$TESTOUT

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
exec 4<$TESTOUT

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
