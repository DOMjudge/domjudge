# Error handling and logging functions
#
# $Id$

logmsg ()
{
	local msglevel stamp msg

	msglevel=$1; shift
	stamp="[`date '+%b %d %T'`] $PROGNAME[$$]:"
	msg="$@"

	if [ $msglevel -le "$VERBOSE"  ]; then
		echo "$stamp $msg" >&2
	fi
	if [ $msglevel -le "$LOGLEVEL" ]; then
		if [ "$LOGFILE" ]; then
			echo "$stamp $msg" >>$LOGFILE
		fi
		if [ "$SYSLOG"  ]; then
			logger -i -t "$PROGNAME" -p "${SYSLOG#LOG_}.$msglevel" "$msg" &> /dev/null
		fi
	fi
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
