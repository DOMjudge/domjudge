#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgroup_get_cgroup() test using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli, Mode
from libcgroup import Cgroup, Version
import consts
import ftests
import sys
import os


CGNAME = '072cggetcg/childcg'
CONTROLLERS = ['cpu', 'memory', 'io', 'pids']

CGNAME2 = '{}/grandchildcg'.format(CGNAME)


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if Cgroup.cgroup_mode() != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup v2 hierarchy'

    return result, cause


def setup(config):
    CgroupCli.create(config, CONTROLLERS, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    #
    # Test 1 - Ensure all enabled controllers get populated
    #
    cgall = Cgroup(CGNAME, Version.CGROUP_V2)
    cgall.get()

    if len(cgall.controllers) != len(CONTROLLERS):
        result = consts.TEST_FAILED
        tmp_cause = 'Expected {} controllers in cgall but received {}'.format(
                    len(CONTROLLERS), len(cgall.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 2 - Ensure the user can read the "cgroup" pseudo-controller
    #
    cgcg = Cgroup(CGNAME, Version.CGROUP_V2)
    cgcg.add_controller('cgroup')
    cgcg.get()

    if len(cgcg.controllers) != 1 or 'cgroup' not in cgcg.controllers.keys():
        result = consts.TEST_FAILED
        tmp_cause = 'Expected 1 controller in cgcg but received {}'.format(len(cgcg.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 3 - Ensure the user can read a disabled controller
    #
    cgcpuset = Cgroup(CGNAME, Version.CGROUP_V2)
    cgcpuset.add_controller('cpuset')
    cgcpuset.get()

    if len(cgcpuset.controllers) != 1 or 'cpuset' not in cgcpuset.controllers.keys():
        result = consts.TEST_FAILED
        tmp_cause = 'Expected 1 controller in cgcpuset but received {}'.format(
                    len(cgcpuset.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 4 - Ensure the user can read a cgroup with a mix of enabled and hidden controllers
    #
    cgmix = Cgroup(CGNAME, Version.CGROUP_V2)
    cgmix.add_controller('cpuset')
    cgmix.add_controller('cgroup')
    cgmix.add_controller('memory')
    cgmix.get()

    if len(cgmix.controllers) != 3 or 'cpuset' not in cgmix.controllers.keys() or \
       'cgroup' not in cgmix.controllers.keys() or 'memory' not in cgmix.controllers.keys():
        result = consts.TEST_FAILED
        tmp_cause = 'Expected 3 controller in cgmix but received {}'.format(
                    len(cgcpuset.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 5 - Create a parent/child cgroup with no controllers enabled.  Ensure the user can get
    # the cgroup with no errors.  .get() should populate zero cgroups
    #
    CgroupCli.subtree_control(config, CGNAME, CONTROLLERS, enable=False, ignore_systemd=True)
    CgroupCli.create(config, None, CGNAME2)

    cgempty = Cgroup(CGNAME2, Version.CGROUP_V2)
    cgempty.get()

    if len(cgempty.controllers) != 0:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected 0 controller in cgempty but received {}'.format(
                    len(cgcpuset.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    CgroupCli.delete(config, CONTROLLERS, CGNAME2, recursive=True)


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
