#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgroup_add_all_controllers() test using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from libcgroup import Cgroup, Version, Mode
from cgroup import Cgroup as CgroupCli
import consts
import ftests
import sys
import os


PARENTCG = '073cgggetcg'
CGNAME = '{}/child'.format(PARENTCG)
CONTROLLERS = ['cpu', 'memory', 'pids']
SUBTREE_CONTROL = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupCli.get_cgroup_mode(config) != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup hierarchy'

    return result, cause


def setup(config):
    CgroupCli.create(config, CONTROLLERS, PARENTCG)

    # Ensure that the subtree control file differs from the cgroup.controllers file
    CgroupCli.subtree_control(config, PARENTCG, CONTROLLERS, enable=False, ignore_systemd=True)
    CgroupCli.subtree_control(config, PARENTCG, SUBTREE_CONTROL, enable=True, ignore_systemd=True)
    CgroupCli.get_and_validate(config, PARENTCG, 'cgroup.subtree_control', SUBTREE_CONTROL, True)

    CgroupCli.create(config, CONTROLLERS, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cgget = Cgroup(CGNAME, Version.CGROUP_V2)
    cgget.get()

    cgall = Cgroup(CGNAME, Version.CGROUP_V2)
    cgall.add_all_controllers()
    cgall.get()

    if len(CONTROLLERS) != len(cgall.controllers):
        result = consts.TEST_FAILED
        tmp_cause = 'Expected {} controllers in cgall but received {}'.format(
                    len(CONTROLLERS), len(cgall.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    if len(str(cgall)) != len(str(cgget)):
        result = consts.TEST_FAILED
        tmp_cause = 'Expected {} lines in cgall but received {}'.format(
                    len(str(cgget)), len(str(cgall)))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    CgroupCli.delete(config, CONTROLLERS, CGNAME)
    CgroupCli.delete(config, CONTROLLERS, PARENTCG)


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
