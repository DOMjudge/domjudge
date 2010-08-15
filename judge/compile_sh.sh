#!/bin/sh

# POSIX shell compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# This script does not actually "compile" the source, but writes a
# shell script that will function as the executable: when called, it
# will execute the source with the correct interpreter syntax, thus
# allowing this interpreted source to be used transparantly as if it
# was compiled to a standalone binary.

SOURCE="$1"
DEST="$2"

RUNOPTIONS=""

# Check for '#!' interpreter line: don't allow it to prevent teams
# from passing options to the interpreter.
if grep '^#!' $SOURCE > /dev/null 2>&1 ; then
	echo "Error: interpreter statement(s) found:"
	grep -n '^#!' $SOURCE
	exit 1
fi

# Check syntax:
sh $RUNOPTIONS -n $SOURCE
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

# Write executing script:
cat > $DEST <<EOF
#!/bin/sh
# Generated shell-script to execute shell interpreter on source.

exec sh $RUNOPTIONS $SOURCE
EOF

chmod a+x $DEST

exit 0
