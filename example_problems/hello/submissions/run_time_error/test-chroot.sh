#!/bin/sh

# This should fail with RUN-ERROR and tests if we're properly in a chroot
# @EXPECTED_RESULTS@: RUN_TIME_ERROR

if [ "$(stat -c %d:%i /)" != "$(stat -c %d:%i /proc/1/root/.)" ]; then
    exit 1
else
    echo "Hello world!"
fi
