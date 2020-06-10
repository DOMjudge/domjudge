#!/bin/sh

# Script to build executables.
#
# Usage: $0 <workdir>

set -e
trap 'cleanup ; error' EXIT

cleanup ()
{
	$DJ_LIBJUDGEDIR/chroot-startstop.sh stop

	# Make sure that all files are owned by the current user/group, so
	# that we can delete the judging output tree without root access.
	# We also remove group RUNGROUP so that this can safely be shared
	# across multiple judgedaemons, and remove write permissions.
	$GAINROOT chown -R "$(id -un):" $WORKDIR
	chmod -R go-w $WORKDIR
#	for i in bin etc lib lib64 proc usr; do
#		rm -rf $i
#	done
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
WORKDIR="$1";
logmsg $LOG_DEBUG "arguments: '$WORKDIR'"

if [ ! -d "$WORKDIR" ] || [ ! -w "$WORKDIR" ] || [ ! -x "$WORKDIR" ]; then
	error "Workdir not found or not writable: $WORKDIR"
fi
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

OLDDIR="$PWD"
cd "$WORKDIR"

$DJ_LIBJUDGEDIR/chroot-startstop.sh start

chmod a+rwx .

logmsg $LOG_INFO "starting compile"

exitcode=0
$GAINROOT "$RUNGUARD" ${DEBUG:+-v} -u "$RUNUSER" -g "$RUNGROUP" \
	-r "$PWD" -- \
	"./build" >"build.log" 2>&1 || \
	exitcode=$?

if [ ! -f ./run ] || [ ! -x ./run ]; then
	echo "Compiling failed: no executable was created; compiler output:" >compile.out
	cat build.log >>compile.out
	cleanexit ${E_COMPILER_ERROR:-1}
fi

logmsg $LOG_INFO "Compilation successful"
cleanexit 0
