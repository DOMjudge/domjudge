#!/bin/bash
# $Id$

# Needs bash because it includes lib.error.sh
#
# Part of the DOMjudge Programming Contest Jury System and licenced
# under the GNU GPL. See README and COPYING for details.

# Global configuration
. "`dirname $0`/../etc/config.sh"

# Error and logging functions
. "$SYSTEM_ROOT/lib/lib.error.sh"

PROGNAME="`basename $0`"

[ $# -eq 2 ] || exit 1

team=$1
fromfile=$2

# TODO: sanity check team and fromfile

logmsg $LOG_INFO "executing: 'scp -Bq ${team}@${SCP_HOST}:'${fromfile}' $DJTMPDIR'"

output=`scp -Bq "${team}@${SCP_HOST}:'${fromfile}'" "$DJTMPDIR" 2>&1`
if [ $? -eq 0 -a ${#output} -eq 0 ]; then
	rm -f "$DJTMPDIR$fromfile"
	exit 0
fi

error "$output"
exit 1
