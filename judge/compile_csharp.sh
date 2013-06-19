#!/bin/sh

# C# compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script byte-compiles with the GNU mono compiler and generates
# a shell script to run it with the mono CLI code generator later.
#
# NOTICE: this compiler script cannot be used with the USE_CHROOT
# configuration option turned on, unless proper preconfiguration of
# the chroot environment has been taken care of!

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

DESTCLI="${DEST}.exe"

SOURCEDIR="${MAINSOURCE%/*}"
[ "$SOURCEDIR" = "$MAINSOURCE" ] && SOURCEDIR='.'

# Byte-compile:
gmcs -o+ -d:ONLINE_JUDGE,DOMJUDGE -out:"$DESTCLI" "$@"
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Check for output file:
if [ ! -f "$DESTCLI" ]; then
	echo "Error: byte-compiled file '$DESTCLI' not found."
	exit 1
fi

# Write executing script, executes mono on generated CLI code:
cat > $DEST <<EOF
#!/bin/sh
# Generated shell-script to execute mono on CLI code.

# Detect dirname and change dir to prevent class not found errors.
if [ "\${0%/*}" != "\$0" ]; then
	cd "\${0%/*}"
fi

exec mono $DESTCLI
EOF

chmod a+x $DEST

exit 0
