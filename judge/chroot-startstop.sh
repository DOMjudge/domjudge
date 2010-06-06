#!/bin/sh
# $Id$

# Script to setup chroot environment extras needed for Sun Java.
#
# Configure the use of this script in 'etc/judgehost-config.php' when
# using the chroot environment and Sun Java compiler/interpreter and
# adapt this script to your environment. See also bin/dj_make_chroot.sh
# for a script to generate a minimal chroot environment with Sun Java.
#
# This script will be called from testcase_run.sh in the root
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
		sudo -S mount -n -t proc --bind /proc proc < /dev/null

		for i in $SUBDIRMOUNTS ; do

			# Some dirs may be links to others, e.g. /lib64 -> /lib.
			# Preserve those; bind mount the others.
			if [ -L "$CHROOTORIGINAL/$i" ]; then
				ln -s `readlink "$CHROOTORIGINAL/$i"` $i
			else 
				mkdir -p $i
				sudo -S mount --bind "$CHROOTORIGINAL/$i" $i < /dev/null
			fi
		done
		;;

	stop)

# Wait a second to assure that no files are accessed anymore:
		sleep 1

		sudo -S umount "$PWD/proc" < /dev/null

		for i in $SUBDIRMOUNTS ; do
			if [ -L "$CHROOTORIGINAL/$i" ]; then
				rm -f $i
			else
				sudo -S umount "$PWD/$i" < /dev/null
				rmdir $i || true
			fi
		done
		;;

	*)
		echo "Unknown argument '$1' given."
		exit 1
esac

exit 0
