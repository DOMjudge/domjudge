#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgxset functionality test using the python bindings
#
# Copyright (c) 2021-2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import CgroupVersion as CgroupCliVersion
from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup, Version
from run import Run
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '040bindings'

SETTING1 = 'cpu.shares'
VALUE1 = '512'

SETTING2 = 'cpu.weight'
VALUE2 = '50'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    user_name = Run.run('whoami', shell_bool=True)
    group_name = Run.run('groups', shell_bool=True).split(' ')[0]

    CgroupCli.create(config, controller_list=CONTROLLER, cgname=CGNAME,
                     user_name=user_name, group_name=group_name)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg1 = Cgroup(CGNAME, Version.CGROUP_V1)
    cg1.add_controller(CONTROLLER)
    cg1.add_setting(SETTING1, VALUE1)

    cg1.cgxset()

    value_v1 = CgroupCli.xget(
                                config, setting=SETTING1, print_headers=False,
                                values_only=True,
                                version=CgroupCliVersion.CGROUP_V1,
                                cgname=CGNAME
                             )

    if value_v1 != VALUE1:
        result = consts.TEST_FAILED
        cause = 'Expected {}, but received {}'.format(VALUE1, value_v1)
        return result, cause

    # Set the cpu.shares/cpu.weight to an arbitrary value to ensure
    # the following v2 cgxset works properly
    CgroupCli.xset(config, cgname=CGNAME, setting=SETTING1, value='1234',
                   version=CgroupCliVersion.CGROUP_V1)

    cg2 = Cgroup(CGNAME, Version.CGROUP_V2)
    cg2.add_controller(CONTROLLER)
    cg2.add_setting(SETTING2, VALUE2)

    cg2.cgxset()

    value_v2 = CgroupCli.xget(
                                config, setting=SETTING2, print_headers=False,
                                values_only=True,
                                version=CgroupCliVersion.CGROUP_V2,
                                cgname=CGNAME
                             )

    if value_v2 != VALUE2:
        result = consts.TEST_FAILED
        cause = 'Expected {}, but received {}'.format(VALUE2, value_v2)

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
