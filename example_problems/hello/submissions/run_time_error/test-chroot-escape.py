'''
This should fail with RUN-ERROR and tests if we're properly in a chroot.
This tries to escape the chroot, which works when we run as root.
@EXPECTED_RESULTS@: RUN_TIME_ERROR
'''

import os

def is_chroot():
    sl_stat = os.stat('/')
    pr_stat = os.stat('/proc/1/root/.')

    return (sl_stat.st_ino != pr_stat.st_ino ) and (sl_stat.st_dev != pr_stat.st_dev )

# We should be in the chroot
if is_chroot():
    # We run as non-root, this should fail
    os.chroot('/proc/1/root')
    if is_chroot():
        # We should still be in the chroot
        os.exit(1)

# If we get here we failed
print("Hello world!")

