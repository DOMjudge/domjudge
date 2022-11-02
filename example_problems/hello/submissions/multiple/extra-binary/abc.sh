#!/bin/sh

# This program tries to execute a submitted version of 'bc'. Although
# this in itself is not very harmful (in a chroot, bc is probably
# available anyways), it does indicate a larger security risk:
# contestants can submit binaries compiled from any language, thereby
# overriding the language restrictions of the contest.
#
# @EXPECTED_RESULTS@: COMPILER-ERROR,RUN-ERROR

set -e

DIR=.
if [ "x${0%/*}" != "x$0" ]; then
	DIR=${0#/*}
fi

BC="$DIR/bc"

# If bc is not executable, then directly call the Linux dynamic loader
# on it as a workaround, or if not available, try chmod:
if [ ! -x $BC ]; then
	if [ -x /lib/ld-linux.so.2 ]; then
		BC="/lib/ld-linux.so.2 $BC"
	else
		chmod u+x "$BC"
	fi
fi

echo "We are '$0' with arguments '$*'."
echo "calculating '10 + 2' using our submitted bc as '$BC':"

$BC <<EOF
10 + 2
EOF

exit 0
