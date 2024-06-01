#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgxget functionality test using the python bindings
#
# Copyright (c) 2021-2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup, Version
from cgroup import CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '039bindings'

SETTING1 = 'cpu.shares'
VALUE1 = '4096'

SETTING2 = 'cpu.weight'
VALUE2 = '400'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    CgroupCli.create(config, CONTROLLER, CGNAME)
    if CgroupVersion.get_version('cpu') == CgroupVersion.CGROUP_V1:
        CgroupCli.set(config, CGNAME, SETTING1, VALUE1)
    else:
        CgroupCli.set(config, CGNAME, SETTING2, VALUE2)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg1 = Cgroup(CGNAME, Version.CGROUP_V1)
    cg1.add_controller(CONTROLLER)
    cg1.add_setting(SETTING1)

    cg1.cgxget()

    if len(cg1.controllers) != 1:
        result = consts.TEST_FAILED
        cause = (
                    "Controller length doesn't match, expected 1, but "
                    "received {}"
                    "".format(len(cg1.controllers))
                )
        return result, cause

    if len(cg1.controllers[CONTROLLER].settings) != 1:
        result = consts.TEST_FAILED
        cause = (
                    "Settings length doesn't match, expected 1, but "
                    " received {}"
                    "".format(len(cg1.controllers[CONTROLLER].settings))
                )
        return result, cause

    if cg1.controllers[CONTROLLER].settings[SETTING1] != VALUE1:
        result = consts.TEST_FAILED
        cause = (
                    'Expected {} = {} but received {}'
                    ''.format(SETTING1, VALUE1,
                              cg1.controllers[CONTROLLER].settings[SETTING1])
                )
        return result, cause

    cg2 = Cgroup(CGNAME, Version.CGROUP_V2)
    cg2.add_controller(CONTROLLER)
    cg2.add_setting(SETTING2)

    cg2.cgxget()

    if len(cg2.controllers) != 1:
        result = consts.TEST_FAILED
        cause = (
                    "Controller length doesn't match, expected 1, but"
                    " received {}"
                    "".format(len(cg2.controllers))
                )
        return result, cause

    if len(cg2.controllers[CONTROLLER].settings) != 1:
        result = consts.TEST_FAILED
        cause = (
                    "Settings length doesn't match, expected 1, but"
                    "received {}"
                    "".format(len(cg2.controllers[CONTROLLER].settings))
                )
        return result, cause

    if cg2.controllers[CONTROLLER].settings[SETTING2] != VALUE2:
        result = consts.TEST_FAILED
        cause = (
                    'Expected {} = {} but received {}'
                    ''.format(SETTING2, VALUE2,
                              cg2.controllers[CONTROLLER].settings[SETTING2])
                )

    return result, cause


def teardown(config):
    CgroupCli.delete(config, CONTROLLER, CGNAME)


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
