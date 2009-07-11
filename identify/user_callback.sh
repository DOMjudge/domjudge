#!/bin/sh
# $Id$

# Script to check for a file on a team account.
# Usage: $0 <team> <filename>
#
# Where <team> is the account of the team and <filename> the file to
# check for with path relative to the user's homedirectory.
#
# This script will depend very much on the setup of your system:
# what kind of filesystems do you have, how can you access the team
# accounts, etc...  Rewrite to fit your needs.
#
# Part of the DOMjudge Programming Contest Jury System and licenced
# under the GNU GPL. See README and COPYING for details.

PROGNAME="`basename $0`"

SCP_HOST=localhost

error ()
{
	echo "$PROGNAME: error: $@"
	exit 127
}

[ $# -eq 2 ] || error  "invalid number of arguments"

team=$1
file=$2

logmsg $LOG_INFO "executing: 'scp -Bq ${team}@${SCP_HOST}:'${file}' /tmp'"

output=`scp -Bq "${team}@${SCP_HOST}:'${file}'" /tmp 2>&1`
if [ $? -eq 0 -a ${#output} -eq 0 ]; then
	rm -f /tmp/`basename "$file"`
	exit 0
fi

error "$output"
exit 1
