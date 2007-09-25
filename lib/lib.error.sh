# Error handling and logging functions
#
# $Id$

logdate ()
{
	date '+%b %d %T'
}

logmsg ()
{
	local msglevel msgstamp msgstring
	msglevel=$1; shift
	msgstamp="[`logdate`]"
	msgstring="$PROGNAME[$$]: $@"
	if [ $msglevel -le "$VERBOSE"  ]; then
		echo "$msgstamp $msgstring" >&2
	fi
	if [ $msglevel -le "$LOGLEVEL" ]; then
		if [ "$LOGFILE" ]; then
			echo "$msgstamp $msgstring" >>$LOGFILE
		fi
		if [ "$SYSLOG_FACILITY" ]; then
			logger -p ${SYSLOG_FACILITY#LOG_}.$msglevel "$msgstring" 2>& 1>/dev/null 
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
