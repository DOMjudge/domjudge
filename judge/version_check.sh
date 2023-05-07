#!/bin/sh

# Script to verify compiler versions.

# Exit automatically, whenever a simple command fails and trap it:
set -e
trap error EXIT

cleanexit ()
{
	trap - EXIT

	chmod go= "$WORKDIR/vcheck" "$WORKDIR/vcheck-script"
	logmsg $LOG_DEBUG "exiting, code = '$1'"
	exit $1
}

# Error and logging functions
# shellcheck disable=SC1090
. "$DJ_LIBDIR/lib.error.sh"

# Logging:
LOGFILE="$DJ_LOGDIR/judge.$(hostname | cut -d . -f 1).log"
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

# Location of scripts/programs:
SCRIPTDIR="$DJ_LIBJUDGEDIR"
GAINROOT="sudo -n"
RUNGUARD="$DJ_BINDIR/runguard"

logmsg $LOG_INFO "starting '$0', PID = $$"

[ $# -ge 2 ] || error "not enough arguments. See script-code for usage."
VERSION_CHECK_SCRIPT="$1";    shift
WORKDIR="$1"; shift
logmsg $LOG_DEBUG "arguments: '$VERSION_CHECK_SCRIPT' '$WORKDIR'"

if [ ! -d "$WORKDIR" ] || [ ! -w "$WORKDIR" ] || [ ! -x "$WORKDIR" ]; then
	error "Workdir not found or not writable: $WORKDIR"
fi
[ -x "$VERSION_CHECK_SCRIPT" ] || error "compile script not found or not executable: $VERSION_CHECK_SCRIPT"
[ -x "$RUNGUARD" ] || error "runguard not found or not executable: $RUNGUARD"

OLDDIR="$PWD"
cd "$WORKDIR"

# Make compile dir accessible and writable for RUNUSER:
mkdir -p "$WORKDIR/vcheck"
chmod a+rwx "$WORKDIR/vcheck"

# Create files which are expected to exist: compiler output and runtime
touch vcheck.out vcheck.meta

# Copy compile script into chroot
# shellcheck disable=SC2174
if [ -e "$WORKDIR/vcheck-script" ]; then
    mv "$WORKDIR/vcheck-script" "$WORKDIR/vcheck-script-old"
fi
mkdir -m 0777 -p "$WORKDIR/vcheck-script"
cp -a "$VERSION_CHECK_SCRIPT" "$PWD/vcheck-script/"

cd "$WORKDIR/vcheck"

logmsg $LOG_INFO "starting version checking"

if [ -n "$DEBUG" ]; then
	ENVIRONMENT_VARS="$ENVIRONMENT_VARS -V DEBUG=$DEBUG"
fi

exitcode=0
$GAINROOT "$RUNGUARD" ${DEBUG:+-v} $CPUSET_OPT -u "$RUNUSER" -g "$RUNGROUP" \
	-r "$PWD/.." -d "/vcheck" \
	-m $SCRIPTMEMLIMIT -t $SCRIPTTIMELIMIT -c -f $SCRIPTFILELIMIT -s $SCRIPTFILELIMIT \
	-M "$WORKDIR/vcheck.meta" $ENVIRONMENT_VARS -- \
	"/vcheck-script/$(basename $VERSION_CHECK_SCRIPT)" >"$WORKDIR/vcheck.tmp" 2>&1 || \
	exitcode=$?

# Make sure that all files are owned by the current user/group, so
# that we can delete the judging output tree without root access.
# We also remove group RUNGROUP so that this can safely be shared
# across multiple judgedaemons, and remove write permissions.
$GAINROOT chown -R "$(id -un):" "$WORKDIR/vcheck"
chmod -R go-w "$WORKDIR/vcheck"

cd "$WORKDIR"

if [ $exitcode -ne 0 ] && [ ! -s vcheck.meta ]; then
	echo "internal-error: runguard crashed" > vcheck.meta
	echo "Runguard exited with code $exitcode and 'vcheck.meta' is empty, it likely crashed." >vcheck.out
	echo "Version check output:" >>vcheck.out
	cat vcheck.tmp >>vcheck.out
	cleanexit ${E_INTERNAL_ERROR:-1}
fi

if [ $exitcode -ne 0 ]; then
	echo "Version checking failed with exitcode $exitcode, version check output:" >vcheck.out
	cat vcheck.tmp >>vcheck.out
	cleanexit ${E_COMPILER_ERROR:-1}
fi
cat vcheck.tmp >>vcheck.out

logmsg $LOG_INFO "Version check successful"
cleanexit 0
