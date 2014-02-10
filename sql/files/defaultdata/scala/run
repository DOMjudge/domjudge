#!/bin/sh

# Scala compile wrapper-script for 'compile.sh'.
# See that script for syntax and more info.
#
# NOTICE: this compiler script cannot be used with the USE_CHROOT
# configuration option turned on, unless proper preconfiguration of
# the chroot environment has been taken care of!

DEST="$1" ; shift
MEMLIMIT="$1" ; shift
MAINSOURCE="$1"

scalac "$@"
EXITCODE=$?
[ "$EXITCODE" -ne 0 ] && exit $EXITCODE

MAINCLASS=$(basename $MAINSOURCE .scala)

cat > $DEST <<EOF
#!/bin/sh
# Generated shell-script to execute scala interpreter on source.

# Detect dirname and change dir to prevent class not found errors.
if [ "\${0%/*}" != "\$0" ]; then
    cd "\${0%/*}"
fi

exec scala $MAINCLASS
EOF

chmod a+x $DEST

exit 0
