#!/bin/bash

# Error handling and logging functions
#
# $Id$

logdate ()
{
	date '+%b %d %T'
}

logmsg ()
{
	local msglevel msgstring
	msglevel=$1; shift
	msgstring="[`logdate`] $PROGNAME[$$]: $@"
	if [ $msglevel -le "$VERBOSE"  ]; then echo "$msgstring" >&2 ; fi
	if [ $msglevel -le "$LOGLEVEL" ]; then echo "$msgstring" >>$LOGFILE ; fi
}

error ()
{
	set +e
	trap - ERR

	if [ "$@" ]; then
		logmsg $LOG_ERR "error: $@"
	else
		logmsg $LOG_ERR "unexpected error, aborting!"
	fi

	exit $E_INTERN
}

warning ()
{
	logmsg $LOG_WARNING "warning: $@"
}
