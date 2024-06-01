#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - exercise the '-a' flag
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER1 = 'memory'
CONTROLLER2 = 'cpuset'
CGNAME = '014cgget'


def prereqs(config):
    pass


def setup(config):
    ver1 = CgroupVersion.get_version(CONTROLLER1)
    ver2 = CgroupVersion.get_version(CONTROLLER2)

    if ver1 == CgroupVersion.CGROUP_V2 and \
       ver2 == CgroupVersion.CGROUP_V2:
        # If both controllers are cgroup v2, then we only need to make
        # one cgroup.  The path will be the same for both
        Cgroup.create(config, [CONTROLLER1, CONTROLLER2], CGNAME)
    else:
        Cgroup.create(config, CONTROLLER1, CGNAME)
        Cgroup.create(config, CONTROLLER2, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    out = Cgroup.get(config, cgname=CGNAME, all_controllers=True)

    # arbitrary check to ensure we read several lines
    if len(out.splitlines()) < 20:
        result = consts.TEST_FAILED
        cause = (
                    'Expected multiple lines, but only received {}'
                    ''.format(len(out.splitlines()))
                )
        return result, cause

    # arbitrary check for a setting that's in both cgroup v1 and cgroup v2
    # memory.stat
    if '\tpgmajfault' not in out:
        result = consts.TEST_FAILED
        cause = 'Unexpected output\n{}'.format(out)
        return result, cause

    # make sure that a cpuset value was in the output:
    if 'cpuset.cpus' not in out:
        result = consts.TEST_FAILED
        cause = 'Failed to find cpuset settings in output\n{}'.format(out)

    return result, cause


def teardown(config):
    ver1 = CgroupVersion.get_version(CONTROLLER1)
    ver2 = CgroupVersion.get_version(CONTROLLER2)

    if ver1 == CgroupVersion.CGROUP_V2 and \
       ver2 == CgroupVersion.CGROUP_V2:
        # If both controllers are cgroup v2, then we only need to make
        # one cgroup.  The path will be the same for both
        Cgroup.delete(config, [CONTROLLER1, CONTROLLER2], CGNAME)
    else:
        Cgroup.delete(config, CONTROLLER1, CGNAME)
        Cgroup.delete(config, CONTROLLER2, CGNAME)


def main(config):
    prereqs(config)
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
