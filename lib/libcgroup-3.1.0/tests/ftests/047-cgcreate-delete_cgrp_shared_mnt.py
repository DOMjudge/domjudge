#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test deleting cgroup on mount point shared by cgroup v1 controllers
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLER1 = 'cpu'
CONTROLLER2 = 'cpuacct'

CGNAME = '047shared_mnts'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'

    # cpuacct controller is only available on cgroup v1, if an exception
    # gets raised, then no cgroup v1 controllers mounted.
    try:
        CgroupVersion.get_version('cpuacct')
    except IndexError:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpuacct controller'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER1, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    try:
        Cgroup.delete(config, CONTROLLER2, CGNAME)
    except RunError as re:
        if 'No such file or directory' in re.stderr:
            cause = 'cpu and cpuacct controllers do not share mount points.'
            result = consts.TEST_FAILED
        else:
            raise re

    try:
        Cgroup.delete(config, CONTROLLER1, CGNAME)
    except RunError as re:
        if 'No such file or directory' not in re.stderr:
            raise re

    return result, cause


def teardown(config):
    pass


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
