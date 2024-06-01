#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Basic cgsnapshot functionality test
#
# Copyright (c) 2020 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'cpuset'
CGNAME = '005cgsnapshot'
CGSNAPSHOT = """group 005cgsnapshot {
                    cpuset {
                            cpuset.cpus.partition="member";
                            cpuset.mems="";
                            cpuset.cpus="";
                    }
            }"""


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpuset') != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v2 cpuset controller'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    expected = Cgroup.snapshot_to_dict(CGSNAPSHOT)
    actual = Cgroup.snapshot(config, controller=CONTROLLER)

    if expected[CGNAME].controllers[CONTROLLER] != \
       actual[CGNAME].controllers[CONTROLLER]:
        result = consts.TEST_FAILED
        cause = 'Expected cgsnapshot result did not equal actual cgsnapshot'

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


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
