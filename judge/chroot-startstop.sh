#!/bin/sh
# $Id$

# Script to setup chroot environment extras needed for Sun Java.
#
# Configure the use of this script in 'etc/global.cfg' when using the
# chroot environment and Sun Java compiler/interpreter and adapt this
# script to your environment. See also bin/make_chroot.sh for a script
# to generate a minimal chroot environment with Sun Java.
#
# This script will be called from test_solution.sh in the root
# directory of the chroot environment with one parameter: either
# 'start' to setup, or 'stop' to destroy the chroot environment.

# Exit on error:
set -e

# Chroot subdirs needed: (add 'lib64' for amd64 architecture)
SUBDIRMOUNTS="etc lib usr"

# Location where to bind mount from:
CHROOTORIGINAL="/chroot/domjudge"

case "$1" in
	start)
	
# Mount (bind) the proc filesystem (needed by Java for /proc/self/stat):
		sudo mount -n -t proc --bind /proc proc

		for i in $SUBDIRMOUNTS ; do
			mkdir -p $i
			sudo mount --bind "$CHROOTORIGINAL/$i" $i
		done
		;;

	stop)

# Wait a second to assure that no files are accessed anymore:
		sleep 1
		
		sudo umount "$PWD/proc"
		
		for i in $SUBDIRMOUNTS ; do
			sudo umount "$PWD/$i"
		done
		;;

	*)
		echo "Unknown argument '$1' given."
		exit 1
esac

exit 0
