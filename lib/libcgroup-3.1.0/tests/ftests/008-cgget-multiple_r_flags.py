#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - multiple '-r' flags
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
CGNAME = '008cgget'

SETTING1_V1 = 'memory.limit_in_bytes'
SETTING1_V2 = 'memory.max'
VALUE1 = '1048576'

SETTING2_V1 = 'memory.soft_limit_in_bytes'
SETTING2_V2 = 'memory.high'
VALUE2 = '1024000'


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        Cgroup.set(config, CGNAME, SETTING1_V1, VALUE1)
        Cgroup.set(config, CGNAME, SETTING2_V1, VALUE2)
    elif version == CgroupVersion.CGROUP_V2:
        Cgroup.set(config, CGNAME, SETTING1_V2, VALUE1)
        Cgroup.set(config, CGNAME, SETTING2_V2, VALUE2)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        settings = [SETTING1_V1, SETTING2_V1]
    elif version == CgroupVersion.CGROUP_V2:
        settings = [SETTING1_V2, SETTING2_V2]

    out = Cgroup.get(config, controller=None, cgname=CGNAME, setting=settings)

    if out.splitlines()[0] != '{}:'.format(CGNAME):
        result = consts.TEST_FAILED
        cause = (
                    'cgget expected the cgroup name {} in the first line.\n'
                    'Instead it received {}'
                    ''.format(CGNAME, out.splitlines()[0])
                )

    if out.splitlines()[1] != '{}: {}'.format(settings[0], VALUE1):
        result = consts.TEST_FAILED
        cause = (
                    'cgget expected the following:\n\t{}: {}\n'
                    'but received:\n\t{}'
                    ''.format(settings[0], VALUE1, out.splitlines()[1])
                )

    if out.splitlines()[2] != '{}: {}'.format(settings[1], VALUE2):
        result = consts.TEST_FAILED
        cause = (
                    'cgget expected the following:\n\t{}: {}\n'
                    'but received:\n\t{}'
                    ''.format(settings[1], VALUE2, out.splitlines()[2])
                )

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


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
