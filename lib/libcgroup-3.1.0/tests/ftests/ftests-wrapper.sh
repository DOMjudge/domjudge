#!/bin/bash
# SPDX-License-Identifier: LGPL-2.1-only

# the lock file is removed after all the tests complete
function cleanup()
{
	sudo rm -f "$RUNNER_LOCK_FILE"
	exit "$1"
}

AUTOMAKE_SKIPPED=77
AUTOMAKE_HARD_ERROR=99

# synchronize between different github runners running on
# same VM's, this will stop runners from stomping over
# each other's run.
LIBCGROUP_RUN_DIR="/var/run/libcgroup/"
RUNNER_LOCK_FILE="/var/run/libcgroup/github-runner.lock"
RUNNER_SLEEP_SECS=300		# sleep for 5 minutes
RUNNER_MAX_TRIES=10		# Abort after 50 minutes, if we don't chance to run

START_DIR=$PWD
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )

if [ "$START_DIR" != "$SCRIPT_DIR" ]; then
	cp "$SCRIPT_DIR"/*.py "$START_DIR"
fi

PYTHON_LIBRARY_PATH=(../../src/python/build/lib*)
if [ -d  "${PYTHON_LIBRARY_PATH[0]}" ]; then
	pushd "${PYTHON_LIBRARY_PATH[0]}" || cleanup $AUTOMAKE_HARD_ERROR
	PYTHONPATH="$PYTHONPATH:$(pwd)"
	export PYTHONPATH
	popd || cleanup $AUTOMAKE_HARD_ERROR
fi

# If other runners are running then the file exists
# let's wait for 5 minutes
time_waited=0
pretty_time=0
while [ -f "$RUNNER_LOCK_FILE" ]; do
	if [ "$RUNNER_MAX_TRIES" -le 0 ]; then
		echo "Unable to get lock to run the ftests, aborting"
		exit 1
	fi

	RUNNER_MAX_TRIES=$(( RUNNER_MAX_TRIES - 1 ))
	sleep "$RUNNER_SLEEP_SECS"

	time_waited=$(( time_waited + RUNNER_SLEEP_SECS ))
	pretty_time=$(echo $time_waited | awk '{printf "%d:%02d:%02d", $1/3600, ($1/60)%60, $1%60}')
	echo "[$pretty_time] Waiting on other runners to complete, $RUNNER_MAX_TRIES retries left"
done

# take the lock and start executing
sudo mkdir -p "$LIBCGROUP_RUN_DIR"
sudo touch "$RUNNER_LOCK_FILE"

./ftests.py -l 10 -L "$START_DIR/ftests.py.log" -n Libcg"$RANDOM"
RET1=$?

./ftests.py -l 10 -L "$START_DIR/ftests-nocontainer.py.log" --no-container \
	-n Libcg"$RANDOM"
RET2=$?

if [ -z "$srcdir" ]; then
	# $srcdir is set by automake but will likely be empty when run by hand and
	# that's fine
	srcdir=""
else
	srcdir=$srcdir"/"
fi

sudo cp $srcdir../../src/libcgroup_systemd_idle_thread /bin
sudo PYTHONPATH="$PYTHONPATH" ./ftests.py -l 10 -s "sudo" \
	-L "$START_DIR/ftests-nocontainer.py.sudo.log" --no-container -n Libcg"$RANDOM"
RET3=$?
sudo rm /bin/libcgroup_systemd_idle_thread

if [ "$START_DIR" != "$SCRIPT_DIR" ]; then
	rm -f "$START_DIR"/*.py
	rm -fr "$START_DIR"/__pycache__
	rm -f ftests.py.log
	rm -f ftests-nocontainer.py.log
	rm -f ftests-nocontainer.py.sudo.log
fi

if [[ $RET1 -ne $AUTOMAKE_SKIPPED ]] && [[ $RET1 -ne 0 ]]; then
	# always return errors from the first test run
	cleanup $RET1
fi
if [[ $RET2 -ne $AUTOMAKE_SKIPPED ]] && [[ $RET2 -ne 0 ]]; then
	# return errors from the second test run
	cleanup $RET2
fi
if [[ $RET3 -ne $AUTOMAKE_SKIPPED ]] && [[ $RET3 -ne 0 ]]; then
	# return errors from the third test run
	cleanup $RET3
fi

if [[ $RET1 -eq 0 ]] || [[ $RET2 -eq 0 ]] || [[ $RET3 -eq 0 ]]; then
	cleanup 0
fi

if [[ $RET1 -eq $AUTOMAKE_SKIPPED ]] || [[ $RET2 -eq $AUTOMAKE_SKIPPED ]] ||
   [[ $RET3 -eq $AUTOMAKE_SKIPPED ]]; then
	cleanup $AUTOMAKE_SKIPPED
fi

# I don't think we should ever get here, but better safe than sorry
cleanup $AUTOMAKE_HARD_ERROR
