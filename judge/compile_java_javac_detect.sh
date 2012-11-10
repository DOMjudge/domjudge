#!/bin/sh

# Java compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script byte-compiles with the Oracle (Sun) javac compiler and
# generates a shell script to run it with the java interpreter later.
# It autodetects the main class name and optionally renames the source
# file if the class is public.
#
# NOTICE: this compiler script cannot be used with the USE_CHROOT
# configuration option turned on, unless proper preconfiguration of
# the chroot environment has been taken care of!

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"
MAINCLASS=""

# Amount of memory reserved for the Java virtual machine in kB. The
# default below is just above the maximum memory usage of current
# versions of the jvm, but might need increasing in some cases.
MEMRESERVED=300000

TMPFILE=`mktemp --tmpdir domjudge_javac_output.XXXXXX` || exit 1

# Byte-compile:
javac -d . "$@" 2> "$TMPFILE"
EXITCODE=$?
if [ "$EXITCODE" -ne 0 ]; then
	# Let's see if should have named the .java differently
	PUBLICCLASS=$(sed -n -e '/class .* is public, should be declared in a file named /{s/.*file named //;s/\.java.*//;p;q}' "$TMPFILE")
	if [ -z "$PUBLICCLASS" ]; then
		cat $TMPFILE
		rm -f $TMPFILE
		exit $EXITCODE
	fi
	rm -f $TMPFILE
	echo "Info: renaming main source '$MAINSOURCE' to '$PUBLICCLASS.java'"
	mv "$MAINSOURCE" "$PUBLICCLASS.java"
	javac -d . "$PUBLICCLASS.java"
	EXITCODE=$?
	[ "$EXITCODE" -ne 0 ] && exit $EXITCODE
fi

rm -f $TMPFILE

# Look for class that has the 'main' function:
for cn in $(find * -type f -regex '^.*\.class$' \
		| sed -e 's/\.class$//' -e 's/\//./'); do
	javap -public "$cn" \
	| egrep -q 'public static void main\(java.lang.String(\[\]|\.\.\.)\)' \
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

# Calculate Java program memlimit as MEMLIMIT - max. JVM memory usage:
MEMLIMITJAVA=$(($MEMLIMIT - $MEMRESERVED))

# Write executing script:
# Executes java byte-code interpreter with following options
# -Xmx: maximum size of memory allocation pool
# -Xrs: reduces usage signals by java, because that generates debug
#       output when program is terminated on timelimit exceeded.
cat > $DEST <<EOF
#!/bin/sh
# Generated shell-script to execute java interpreter on source.

# Detect dirname and change dir to prevent class not found errors.
if [ "\${0%/*}" != "\$0" ]; then
	cd "\${0%/*}"
fi

exec java -Xrs -Xss8m -Xmx${MEMLIMITJAVA}k $MAINCLASS
EOF

chmod a+x $DEST

exit 0
