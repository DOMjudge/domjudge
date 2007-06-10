#!/bin/bash
# $Id$

# Script to create directories necessary for the DOMjudge system as
# part of the installation process. This script should be called from
# the SYSTEM_ROOT (or SYSTEM_ROOT/bin) directory!
#
# Normally you don't want to run this script directly, but via 'make'
# instead. It gets as first argument the make target.

# Exit on any error:
set -e

PROGRAM=$0
TARGET=$1

error()
{
	echo "$PROGRAM: $@" >&2
	exit 1
}

if [ -f etc/config.sh ]; then
	source etc/config.sh
elif [ -f ../etc/config.sh ]; then
	source ../etc/config.sh
else
	error "configuration not found: called from right dir?"
fi

case "$TARGET" in
	install)
		mkdir -m 0711 -p $INPUT_ROOT $OUTPUT_ROOT
		mkdir -m 0700 -p $INCOMINGDIR $SUBMITDIR $JUDGEDIR $LOGDIR
		cd $INPUT_ROOT && tar xzf $SYSTEM_ROOT/sample-data/input.tar.gz
		;;
	clean)
		;;
	distclean)
		rm -rf $INPUT_ROOT $OUTPUT_ROOT $INCOMINGDIR $SUBMITDIR $JUDGEDIR $LOGDIR
		;;
	*) error "unknown target: '$TARGET'." ;;
esac

exit 0
