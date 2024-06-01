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

CGNAME = '074cggetcg/child'
CONTROLLERS = ['cpu', 'memory', 'pids']


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupCli.get_cgroup_mode(config) != Mode.CGROUP_MODE_LEGACY:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the legacy cgroup hierarchy'

    return result, cause


def setup(config):
    CgroupCli.create(config, CONTROLLERS, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cgall = Cgroup(CGNAME, Version.CGROUP_V1)
    cgall.add_all_controllers()
    cgall.get()

    controllers = list()
    with open('/proc/cgroups') as pc:
        for i, line in enumerate(pc.readlines()):
            if i == 0:
                continue
            if int(line.split()[1]) > 0:
                # If the hierarchy is greater than zero, then use the controller
                controllers.append(line.split()[0])

    if len(controllers) != len(cgall.controllers):
        result = consts.TEST_FAILED
        tmp_cause = 'Expected {} controllers in cgall but received {}'.format(
                    len(CONTROLLERS), len(cgall.controllers))
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    for controller in CONTROLLERS:
        if len(str(cgall.controllers[controller])) <= 1:
            result = consts.TEST_FAILED
            tmp_cause = 'Controller {} was not populated'.format(controller)
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    CgroupCli.delete(config, CONTROLLERS, CGNAME)


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
