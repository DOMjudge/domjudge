#!/bin/sh

# Script to setup chroot environment extras needed for Oracle Java.
#
# Configure the use of this script in 'etc/judgehost-config.php' when
# using the chroot environment and Oracle Java compiler/interpreter and
# adapt this script to your environment. See also bin/dj_make_chroot.sh
# for a script to generate a minimal chroot environment with Oracle Java.
#
# This script will be called from judgedaemon.main.php in the root
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
		mkdir -p proc
		sudo -n mount -n -t proc --bind /proc proc < /dev/null

		for i in $SUBDIRMOUNTS ; do

			# Some dirs may be links to others, e.g. /lib64 -> /lib.
			# Preserve those; bind mount the others.
			if [ -L "$CHROOTORIGINAL/$i" ]; then
				ln -s `readlink "$CHROOTORIGINAL/$i"` $i
			else
				mkdir -p $i
				sudo -n mount --bind "$CHROOTORIGINAL/$i" $i < /dev/null
				# Mount read-only for extra security. Note that this
				# must be executed separately from the bind mount.
				sudo -n mount -o remount,ro,bind "$PWD/$i" < /dev/null
			fi
		done
		;;

	stop)

# Wait a second to assure that no files are accessed anymore:
		sleep 1

		sudo -n umount "$PWD/proc" < /dev/null
		rmdir proc || true

		for i in $SUBDIRMOUNTS ; do
			if [ -L "$CHROOTORIGINAL/$i" ]; then
				rm -f $i
			else
				sudo -n umount "$PWD/$i" < /dev/null
				rmdir $i || true
			fi
		done
		;;

	*)
		echo "Unknown argument '$1' given."
		exit 1
esac

exit 0
