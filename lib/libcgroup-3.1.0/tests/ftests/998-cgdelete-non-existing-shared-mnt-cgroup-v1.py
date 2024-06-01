#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Cgroup recursive cgdelete functionality test for shared mount point on cgroup v1
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import CgroupVersion, Cgroup
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = 'test'

expected_err = "cgdelete: cannot remove group '%s': No such file or directory" % CGNAME


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'
        return result, cause

    # cpuacct controller is only available on cgroup v1, if an exception
    # gets raised, then no cgroup v1 controllers are mounted.
    try:
        CgroupVersion.get_version('cpuacct')
    except IndexError:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpuacct controller'

    return result, cause


def setup(config):
    return consts.TEST_PASSED, None


def test(config):
    result = consts.TEST_PASSED
    cause = None

    try:
        Cgroup.delete(config, CONTROLLER, CGNAME)
    except RunError as re:
        if expected_err not in re.stderr and re.ret != 82:
            result = consts.TEST_FAILED
            cause = 'Expected {}'.format(expected_err)
            return result, cause

    try:
        Cgroup.delete(config, CONTROLLER, CGNAME, recursive=True)
    except RunError as re:
        if expected_err not in re.stderr and re.ret != 82:
            result = consts.TEST_FAILED
            cause = 'Expected {}'.format(expected_err)

    return result, cause


def teardown(config):
    return consts.TEST_PASSED, None


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
        result = consts.TEST_FAILED
        setup(config)
        [result, cause] = test(config)
    finally:
        teardown(config)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
