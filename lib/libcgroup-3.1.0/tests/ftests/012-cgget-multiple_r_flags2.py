#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - multiple '-r' flags and multiple cgroups
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
CGNAME1 = '012cgget1'
CGNAME2 = '012cgget2'

SETTING1_V1 = 'memory.limit_in_bytes'
SETTING1_V2 = 'memory.max'
VALUE1 = '4194304'

SETTING2_V1 = 'memory.soft_limit_in_bytes'
SETTING2_V2 = 'memory.high'
VALUE2 = '4096000'

EXPECTED_OUT = '''{}:
{}
{}

{}:
{}
{}
'''.format(CGNAME1, VALUE1, VALUE2, CGNAME2, VALUE1, VALUE2)


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME1)
    Cgroup.create(config, CONTROLLER, CGNAME2)

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        Cgroup.set(config, CGNAME1, SETTING1_V1, VALUE1)
        Cgroup.set(config, CGNAME1, SETTING2_V1, VALUE2)
        Cgroup.set(config, CGNAME2, SETTING1_V1, VALUE1)
        Cgroup.set(config, CGNAME2, SETTING2_V1, VALUE2)
    elif version == CgroupVersion.CGROUP_V2:
        Cgroup.set(config, CGNAME1, SETTING1_V2, VALUE1)
        Cgroup.set(config, CGNAME1, SETTING2_V2, VALUE2)
        Cgroup.set(config, CGNAME2, SETTING1_V2, VALUE1)
        Cgroup.set(config, CGNAME2, SETTING2_V2, VALUE2)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        settings = [SETTING1_V1, SETTING2_V1]
    elif version == CgroupVersion.CGROUP_V2:
        settings = [SETTING1_V2, SETTING2_V2]

    out = Cgroup.get(config, controller=None, cgname=[CGNAME1, CGNAME2],
                     setting=settings, values_only=True)

    for line_num, line in enumerate(out.splitlines()):
        if line.strip() != EXPECTED_OUT.splitlines()[line_num].strip():
            result = consts.TEST_FAILED
            cause = (
                        'Expected line:\n\t{}\nbut received line:\n\t{}'
                        ''.format(EXPECTED_OUT.splitlines()[line_num].strip(),
                                  line.strip())
                    )
            return result, cause

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME1)
    Cgroup.delete(config, CONTROLLER, CGNAME2)


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
