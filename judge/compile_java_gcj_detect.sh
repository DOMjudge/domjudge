#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

SOURCE="$1"
DEST="$2"
MAINCLASS=""

TMPFILE=`mktemp /tmp/domjudge_gcj_output.XXXXXX` || exit 1

# Byte-compile:
#   -Wall:  Report all warnings
gcj -d . -Wall -C "$SOURCE" 2> "$TMPFILE"
EXITCODE=$?
if [ "$EXITCODE" -ne 0 ]; then
	# Let's see if should have named the .java differently
	PUBLICCLASS=$(sed -n -e "/Public class '.*' must be defined in a file called /{s/.*file called '//;s/\.java'.*//;p;q}" "$TMPFILE")
	if [ -z "$PUBLICCLASS" ]; then
		cat $TMPFILE
		rm -f $TMPFILE
		exit $EXITCODE
	fi
	rm -f $TMPFILE
	echo "Info: renaming source to '$PUBLICCLASS.java'"
	mv "$SOURCE" "$PUBLICCLASS.java"
	SOURCE="$PUBLICCLASS.java"
	gcj -d . -Wall -C "$SOURCE"
	EXITCODE=$?
	[ "$EXITCODE" -ne 0 ] && exit $EXITCODE
fi

rm -f $TMPFILE

# Look for class that has the 'main' function:
for cn in $(find * -type f -regex '^.*\.class$' \
		| sed -e 's/\.class$//' -e 's/\//./'); do
	jcf-dump -javap "$cn" \
	| grep -q 'public static void "main"(java.lang.String\[\])' \
	&& {
		if [ -n "$MAINCLASS" ]; then
			echo "Warning: found another 'main' in '$cn'"
		else
			echo "Info: using 'main' from '$cn'"
			MAINCLASS=$cn
		fi
	}
done
if [ -z "$MAINCLASS" ]; then
	echo "Error: no 'main' found in any class file."
	exit 1
fi

#Compile source to stand-alone app
# -Wall:	Report all warnings
# -O2:		Level 2 optimizations (default for speed)
# -static:	Static link with all libraries
# -pipe:	Use pipes for communication between stages of compilation
gcj -Wall -O2 -static-libgcj -pipe --main=$MAINCLASS -o $DEST $SOURCE
exit $?
