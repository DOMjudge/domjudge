#!/usr/bin/env python3
#
# cgexec to an empty cgroup functionality test using the python bindings
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from process import Process
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '046cgexec'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    Cgroup.create(config, None, cgname=CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    config.process.create_process_in_cgroup(config, '', CGNAME)
    output = Cgroup.get(
                         config, controller=None, cgname=CGNAME,
                         setting='cgroup.procs', print_headers=False,
                         values_only=False
                        )

    if not len(output):
        result = consts.TEST_FAILED
        cause = 'No process created in the cgroup'

    return result, cause


def teardown(config):
    pids = Cgroup.get_pids_in_cgroup(config, CGNAME, CONTROLLER)
    Process.kill(config, pids)

    Cgroup.delete(config, None, CGNAME)


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    setup(config)
    [result, cause] = test(config)
    teardown(config)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
