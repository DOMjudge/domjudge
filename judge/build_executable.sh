#!/bin/sh

# Script to build executables such as compile, compare and run scripts.
#
# Usage: $0 <chroot_dir>
#
# Starts a chroot at <chroot_dir> and runs /build/build inside it with
# working directory set to /build. This should generate an executable
# /build/run.

set -e
trap 'cleanup ; error' EXIT

cleanup ()
{
	"${DJ_LIBJUDGEDIR}/chroot-startstop.sh" stop

	# Make sure that all files are owned by the current user/group, so
	# that we can delete the judging output tree without root access.
	# We also remove group RUNGROUP so that this can safely be shared
	# across multiple judgedaemons, and remove write permissions.
	$GAINROOT chown -R "$(id -un):" "$WORKDIR"
	chmod -R go-w "$WORKDIR"
}

cleanexit ()
{
	set +e
	trap - EXIT

	cleanup

	logmsg $LOG_DEBUG "exiting, code = '$1'"
	exit $1
}

# Error and logging functions
# shellcheck disable=SC1090
. "$DJ_LIBDIR/lib.error.sh"

LOGLEVEL=$LOG_DEBUG
PROGNAME="$(basename "$0")"

# Check for judge backend debugging:
if [ "$DEBUG" ]; then
	export DEBUG
	export VERBOSE=$LOG_DEBUG
	logmsg $LOG_NOTICE "debugging enabled, DEBUG='$DEBUG'"
else
	export VERBOSE=$LOG_ERR
fi

GAINROOT="sudo -n"
RUNGUARD="$DJ_BINDIR/runguard"

logmsg $LOG_INFO "starting '$0', PID = $$"

[ $# -ge 1 ] || error "not enough arguments. See script-code for usage."
CHROOTDIR="$1";
logmsg $LOG_DEBUG "arguments: '$CHROOTDIR'"

WORKDIR="$CHROOTDIR/build"

if [ ! -d "$WORKDIR" ] || [ ! -w "$WORKDIR" ] || [ ! -x "$WORKDIR" ]; then
	error "Workdir not found or not writable: $WORKDIR"
fi
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

cd "$CHROOTDIR"

"${DJ_LIBJUDGEDIR}/chroot-startstop.sh" start

chmod a+rwx "$WORKDIR"

logmsg $LOG_INFO "starting build"

exitcode=0
$GAINROOT "$RUNGUARD" ${DEBUG:+-v} -u "$RUNUSER" -g "$RUNGROUP" \
	-r "$CHROOTDIR" -d '/build' -- \
	'./build' > 'build.log' 2>&1 || \
	exitcode=$?

if [ $exitcode -ne 0 ]; then
	echo "building failed with exitcode $exitcode" >> 'build.log'
	cleanexit 1
fi
if [ ! -f './build/run' ] || [ ! -x './build/run' ]; then
	echo "building failed: no executable 'run' was created" >> 'build.log'
	cleanexit 1
fi

logmsg $LOG_INFO "building successful"
cleanexit 0
