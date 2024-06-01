#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - multiple '-g' flags
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '016cgget'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    # Github Actions has issues with cgget and the code coverage profiler.
    # This causes issues with the error handling of this test
    if not config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test must be run within a container'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    try:
        # cgget -g cpu
        Cgroup.get(config, controller=CONTROLLER)
    except RunError as re:
        if 'Wrong input parameters,' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#1 Expected 'Wrong input parameters' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#1 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #1 erroneously passed'
        return result, cause

    try:
        # cgget -g cpu:016cgget 016cgget
        Cgroup.get(config, controller='{}:{}'.format(CONTROLLER, CGNAME),
                   cgname=CGNAME)
    except RunError as re:
        if 'Wrong input parameters,' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#2 Expected 'Wrong input parameters' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#2 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #2 erroneously passed'
        return result, cause

    try:
        # cgget -r invalidsetting 016cgget
        Cgroup.get(config, setting='invalidsetting', cgname=CGNAME,
                   print_headers=False, values_only=True)
    except RunError as re:
        if 'cgget: error parsing parameter name' not in re.stderr:
            result = consts.TEST_FAILED
            cause = (
                        "#3 Expected 'cgget: error parsing parameter name' to "
                        "be in stderr"
                    )
            return result, cause

        # legacy cgget returns 0 but populates stderr for this case.
        # This feels wrong, so the updated cgget returns ECGINVAL
        if re.ret != 91 and re.ret != 0:
            result = consts.TEST_FAILED
            cause = (
                        '#3 Expected return code of 0 or 91 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #3 erroneously passed'
        return result, cause

    try:
        # cgget -r invalid.setting 016cgget
        Cgroup.get(config, setting='invalid.setting', cgname=CGNAME,
                   print_headers=False, values_only=True)
    except RunError as re:
        if 'cgget: cannot find controller' not in re.stderr:
            result = consts.TEST_FAILED
            cause = (
                        "#4 Expected 'cgget: cannot find controller' to be in "
                        "stderr"
                    )
            return result, cause

        # legacy cgget returns 0 but populates stderr for this case.
        # This feels wrong, so the updated cgget returns ECGOTHER
        if re.ret != 96 and re.ret != 0:
            result = consts.TEST_FAILED
            cause = (
                        '#4 Expected return code of 0 or 96 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #4 erroneously passed'
        return result, cause

    try:
        # cgget -r cpu.invalid 016cgget
        Cgroup.get(config, setting='{}.invalid'.format(CONTROLLER),
                   cgname=CGNAME, print_headers=False, values_only=True)
    except RunError as re:
        if 'variable file read failed' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#5 Expected 'variable file read failed' to be in stderr"
            return result, cause

        # legacy cgget returns 0 but populates stderr for this case.
        # This feels wrong, so the updated cgget returns ECGOTHER
        if re.ret != 96 and re.ret != 0:
            result = consts.TEST_FAILED
            cause = (
                        '#5 Expected return code of 0 or 96 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #5 erroneously passed'
        return result, cause

    try:
        # cgget with no parameters
        Cgroup.get(config, controller=None, cgname=None, setting=None,
                   print_headers=True, values_only=False,
                   all_controllers=False, cghelp=False)
    except RunError as re:
        if 'Wrong input parameters,' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#6 Expected 'Wrong input parameters' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#6 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #6 erroneously passed'
        return result, cause

    # cgget -h
    ret = Cgroup.get(config, cghelp=True)
    if 'Print parameter(s)' not in ret:
        result = consts.TEST_FAILED
        cause = '#7 Failed to print help text'

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


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
