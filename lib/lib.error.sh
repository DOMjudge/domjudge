# Error handling and logging functions

# Loglevels (as defined in syslog.h)
LOG_EMERG=0
LOG_ALERT=1
LOG_CRIT=2
LOG_ERR=3
LOG_WARNING=4
LOG_NOTICE=5
LOG_INFO=6
LOG_DEBUG=7

# Default verbosity and loglevels:
VERBOSE=$LOG_NOTICE
LOGLEVEL=$LOG_DEBUG

logmsg ()
{
	local msglevel stamp msg

	msglevel="$1"; shift
	msec=`date '+%N'`
	stamp="[`date '+%b %d %T'`.`printf "%.3s" $msec`] $PROGNAME[$$]:"
# Expand backslash escape sequences using printf, since support for
# echo with(out) option -e is unreliable. First use echo to put spaces
# between arguments.
	msg=`printf '%b' "$(echo "$@")"`

	if [ "$msglevel" -le "${VERBOSE:-$LOG_ERR}" ]; then
		echo "$stamp $msg" >&2
	fi
	if [ "$msglevel" -le "${LOGLEVEL:-$LOG_DEBUG}" ]; then
		if [ "$LOGFILE" ]; then
			echo "$stamp $msg" >>$LOGFILE
		fi
		if [ "$SYSLOG" ]; then
			logger -i -t "$PROGNAME" -p "${SYSLOG#LOG_}.$msglevel" "$msg" >/dev/null 2>&1
		fi
	fi
}

error ()
{
	set +e
	trap - EXIT

	if [ "$@" ]; then
		logmsg $LOG_ERR "error: $@"
	else
		logmsg $LOG_ERR "unexpected error, aborting!"
	fi

	exit 127
}

warning ()
{
	logmsg $LOG_WARNING "warning: $@"
}
