#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test invalid parameters for systemd_create_scope2()
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import CgroupVersion as CgroupCliVersion
from libcgroup import Cgroup, Version
import ftests
import consts
import sys
import os

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

    if CgroupCliVersion.get_version(CONTROLLER) != CgroupCliVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg1 = Cgroup("InvalidNameBecauseNoSlash", Version.CGROUP_V2)
    cg1.add_controller(CONTROLLER)
    try:
        cg1.create_scope2()
    except RuntimeError as re:
        if '50011' not in str(re):
            result = consts.TEST_FAILED
            cause = 'Expected ECGINVAL (50011) but received {}'.format(re)
    else:
        result = consts.TEST_FAILED
        cause = 'An invalid cgroup name unexpectedly passed: {}'.format(cg1.name)

    cg2 = Cgroup("Invalid/TooMany/Slashes", Version.CGROUP_V2)
    cg2.add_controller(CONTROLLER)
    try:
        cg2.create_scope2()
    except RuntimeError as re:
        if '50011' not in str(re):
            result = consts.TEST_FAILED
            tmp_cause = 'Expected ECGINVAL (50011) but received {}'.format(re)
            if not cause:
                cause = tmp_cause
            else:
                cause = '{}\n{}'.format(cause, tmp_cause)
    else:
        result = consts.TEST_FAILED
        cause = 'An invalid cgroup name unexpectedly passed: {}'.format(cg1.name)
        if not cause:
            cause = tmp_cause
        else:
            cause = '{}\n{}'.format(cause, tmp_cause)

    return result, cause


def teardown(config, result):
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
