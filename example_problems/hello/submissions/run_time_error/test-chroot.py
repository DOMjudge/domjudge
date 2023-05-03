'''
This should fail with RUN-ERROR and tests if we're properly in a chroot
@EXPECTED_RESULTS@: RUN_TIME_ERROR
'''

import os

sl_stat = os.stat('/')
pr_stat = os.stat('/proc/1/root/.')

if (sl_stat.st_ino != pr_stat.st_ino ) and (sl_stat.st_dev != pr_stat.st_dev ):
    os.exit(1)
print("Hello world!")
