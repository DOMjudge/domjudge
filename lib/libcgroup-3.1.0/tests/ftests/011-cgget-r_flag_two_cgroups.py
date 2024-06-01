#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - '-r' <name> <cgroup1> <cgroup2>
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
CGNAME1 = '011cgget1'
CGNAME2 = '011cgget2'

SETTING_V1 = 'memory.limit_in_bytes'
SETTING_V2 = 'memory.max'
VALUE = '2048000'

EXPECTED_OUT_V1 = '''011cgget1:
memory.limit_in_bytes: 2048000

011cgget2:
memory.limit_in_bytes: 2048000
'''

EXPECTED_OUT_V2 = '''011cgget1:
memory.max: 2048000

011cgget2:
memory.max: 2048000
'''


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME1)
    Cgroup.create(config, CONTROLLER, CGNAME2)

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        Cgroup.set(config, CGNAME1, SETTING_V1, VALUE)
        Cgroup.set(config, CGNAME2, SETTING_V1, VALUE)
    elif version == CgroupVersion.CGROUP_V2:
        Cgroup.set(config, CGNAME1, SETTING_V2, VALUE)
        Cgroup.set(config, CGNAME2, SETTING_V2, VALUE)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    version = CgroupVersion.get_version(CONTROLLER)

    if version == CgroupVersion.CGROUP_V1:
        setting = SETTING_V1
        expected_out = EXPECTED_OUT_V1
    elif version == CgroupVersion.CGROUP_V2:
        setting = SETTING_V2
        expected_out = EXPECTED_OUT_V2

    out = Cgroup.get(config, controller=None, cgname=[CGNAME1, CGNAME2],
                     setting=setting)

    for line_num, line in enumerate(out.splitlines()):
        if line.strip() != expected_out.splitlines()[line_num].strip():
            result = consts.TEST_FAILED
            cause = (
                        'Expected line:\n\t{}\nbut received line:\n\t{}'
                        ''.format(expected_out.splitlines()[line_num].strip(),
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
