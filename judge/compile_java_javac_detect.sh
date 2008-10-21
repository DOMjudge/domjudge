#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script byte-compiles with the Sun javac compiler and generates
# a shell script to run it with the java interpreter later.
#
# NOTICE: this compiler script cannot be used with the USE_CHROOT
# configuration option turned on, unless proper preconfiguration of
# the chroot environment has been taken care of!

SOURCE="$1"
DEST="$2"
MEMLIMIT="$3"

# Sun java needs filename to match main class:
MAINCLASS=""

# Byte-compile:
javac $SOURCE
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Look for class that has the 'main' function:
for fn in *.class; do
	cn=$(basename $fn .class)
	if [ -n "$(javap -public $cn | grep 'public static void main(java.lang.String\[\])')" ]; then
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
