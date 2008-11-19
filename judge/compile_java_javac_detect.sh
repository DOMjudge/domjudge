#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.

SOURCE="$1"
DEST="$2"
MEMLIMIT="$3"
MAINCLASS=""

# Byte-compile:
javac -d . "$SOURCE"
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Look for class that has the 'main' function:
for cn in $(find * -type f -regex '^.*\.class$' \
		| sed -e 's/\.class$//' -e 's/\//./'); do
	javap -public "$cn" \
	| grep -q 'public static void main(java.lang.String\[\])' \
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
MEMLIMITJAVA=$(($MEMLIMIT - 262144))

# Write executing script:
# Executes java byte-code interpreter with following options
# -Xmx: maximum size of memory allocation pool
# -Xrs: reduces usage signals by java, because that generates debug
#       output when program is terminated on timelimit exceeded.
cat > $DEST <<EOF
#!/bin/sh
# Generated shell-script to execute java interpreter on source.

exec java -Xrs -Xmx${MEMLIMITJAVA}k $MAINCLASS
EOF

chmod a+x $DEST

exit 0
