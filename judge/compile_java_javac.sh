#!/bin/bash

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script byte-compiles with the Sun javac compiler and generates
# a shell script to run it with the java interpreter later.
#
# NOTICE: this compiler script cannot be used with the USE_CHROOT
# configuration option turned on! (Unless proper preconfiguration of
# the chroot environment has been taken care of.)

SOURCE="$1"
DEST="$2"

# Sun java needs filename to match main class:
MAINCLASS=Main

SOURCEDIR=${SOURCE%/*}
[ "$SOURCEDIR" = "$SOURCE" ] && SOURCEDIR='.'
TMP="$SOURCEDIR/$MAINCLASS"

cp $SOURCE $TMP.java

# Byte-compile:
javac $TMP.java
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Check for class file:
if [ ! -f "$TMP.class" ]; then
	echo "Error: byte-compiled class file '$TMP.class' not found."
	exit 1
fi

# Write executing script:
cat > $DEST <<EOF
#!/bin/bash
# Generated shell-script to execute java interpreter on source.

exec java $TMP
EOF

chmod a+x $DEST

exit 0
