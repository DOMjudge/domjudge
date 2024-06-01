#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgset functionality test - set multiple values in multiple cgroups
#                                     via the '-r' flag
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'memory'
CGNAMES = ['026cgset1', '026cgset2']

SETTINGS = ['memory.limit_in_bytes',
            'memory.soft_limit_in_bytes',
            'memory.swappiness']
VALUES = ['2048000', '1024000', '89']


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('memory') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 memory controller'

    return result, cause


def setup(config):
    for cg in CGNAMES:
        Cgroup.create(config, CONTROLLER, cg)


def test(config):
    Cgroup.set(config, cgname=CGNAMES, setting=SETTINGS, value=VALUES)

    for i, setting in enumerate(SETTINGS):
        for cg in CGNAMES:
            Cgroup.get_and_validate(config, cg, setting, VALUES[i])

    return consts.TEST_PASSED, None


def teardown(config):
    for cg in CGNAMES:
        Cgroup.delete(config, CONTROLLER, cg)


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
