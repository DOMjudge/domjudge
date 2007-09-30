#!/bin/sh
# $Id$

# Script to copy a submission file from a team to judge account.
# Usage: $0 <team> <fromfile> <tofile>
#
# Where <team> is the account of the team and <fromfile> and <tofile>
# are including full absolute path.
#
# This script will depend very much on the setup of your system:
# what kind of filesystems do you have, how can you access the team
# accounts, etc...  Rewrite to fit your needs.
#
# Part of the DOMjudge Programming Contest Jury System and licenced
# under the GNU GPL. See README and COPYING for details.

# Global configuration
. "`dirname $0`/../etc/config.sh"

# Error and logging functions
. "$SYSTEM_ROOT/lib/lib.error.sh"

PROGNAME="`basename $0`"

[ $# -eq 3 ] || exit 1

team=$1
fromfile=$2
tofile=$3

output=`scp -Bq "${team}@${SCP_HOST}:'${fromfile}'" "$tofile" 2>&1`
if [ $? -eq 0 -a ${#output} -eq 0 ]; then
	exit 0
fi

error "$output"
exit 1
