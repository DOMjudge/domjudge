#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script compiles a statically linked binary with gcj. In
# addition, it removes some warnings that gcj generates by default
# with static compilation. These warnings have confused teams in the
# past.

SOURCE="$1"
DEST="$2"

# Byte-compile:
#   -Wall:  Report all warnings
gcj -Wall -C $SOURCE
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Look for class that has the 'main' function:
for fn in *.class; do
	cn=$(basename $fn .class)
	if [ -n "$(jcf-dump -javap $cn | grep 'public static void "main"(java.lang.String\[\])')" ]; then
		if [ -n "$MAINCLASS" ]; then
			echo "Warning: found another 'main' in class $vn"
		else
			echo "Info: using 'main' from class $cn"
			MAINCLASS=$cn
		fi
	fi
done
if [ -z "$MAINCLASS" ]; then
	echo "Error: no 'main' found in any class file."
	exit 1
fi

#Compile the bytecode to stand-alone app
#   -Wall:   Report all warnings
#	-static: Static link with all libraries
gcj -Wall -O2 -static --main=$MAINCLASS -o $DEST *.class
exit $?
