#!/bin/bash
# $Id$

# Script to copy a submission file from a team to judge account.
# Usage: $0 <team> <fromfile> <tofile>

# Global configuration
source "`dirname $0`/../etc/config.sh"

function error()
{
    echo "[`date '+%b %d %T'`] $0[$$]: $@"
}

[ $# -eq 3 ] || exit 1

team="$1"
fromfile="$2"
tofile="$3"

output1=`scp -Bq "${team}@${SCP_HOST}:${fromfile}" "$tofile" 2>&1`
if [ $? -eq 0 -a ${#output1} -eq 0 ]; then
	exit 0
else
	homedir="${fromfile%%.submit*}"
	fromfile="${fromfile#*.submit}"
	fromfile="${homedir}cygwin/.submit${fromfile}"

	output2=`scp -Bq "${team}@${SCP_HOST}:${fromfile}" "$tofile" 2>&1`
	[ $? -eq 0 -a ${#output2} -eq 0 ] && exit 0
fi

error "$output1"
error "$output2"
exit 1
