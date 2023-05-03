#!/bin/sh

# This should fail with RUN-ERROR and tests if we're properly in a chroot.
# This tries to escape the chroot, which works when we run as root.
# @EXPECTED_RESULTS@: RUN_TIME_ERROR

ischroot
if [ ischroot ]; then
    # We expect to be in the jail
    chroot /proc/1/root
    if [ ischroot ]; then
        # We expect to still be in the jail
        exit 1
    else
        echo "Hello world!"
    fi
else
    echo "Hello world!"
fi
