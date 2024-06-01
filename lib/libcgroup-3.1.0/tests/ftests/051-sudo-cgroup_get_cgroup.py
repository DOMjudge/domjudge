#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Exercise cgroup_create_cgroup() and cgroup_get_cgroup()
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from libcgroup import Cgroup, Version
from cgroup import CgroupVersion
import ftests
import consts
import sys
import os

CGNAME = '051cgnewcg/childcg'

# Which controller isn't all that important, but it is important that we
# have a cgroup v2 controller
CONTROLLER = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.add_controller(CONTROLLER)
    cg.create()


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.get()

    if len(cg.controllers) != 1:
        # only one controller, cpu, should be enabled
        result = consts.TEST_FAILED
        cause = 'Expected one controller to be enabled, but {} were enabled'.format(
                len(cg.controllers))

    return result, cause


def teardown(config, result):
    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    try:
        cg.delete()
    except RuntimeError:
        pass


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
        result = consts.TEST_FAILED
        setup(config)
        [result, cause] = test(config)
    finally:
        teardown(config, result)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
