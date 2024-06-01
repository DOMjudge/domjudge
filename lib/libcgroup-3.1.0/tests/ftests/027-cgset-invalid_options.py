#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgset functionality test - invalid options
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
CGNAME1 = '027cgset1'
CGNAME2 = '027cgset2'


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME1)
    Cgroup.create(config, CONTROLLER, CGNAME2)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    try:
        # cgset -r cpu.shares=100 --copy-from 027cgset2 027cgset1
        Cgroup.set(config, cgname=CGNAME1, setting='cpu.shares', value='100',
                   copy_from=CGNAME2)
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
        # cgset -r cpu.shares=100
        Cgroup.set(config, cgname=None, setting='cpu.shares', value='100')
    except RunError as re:
        if 'cgset: no cgroup specified' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#2 Expected 'no cgroup specified' to be in stderr"
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
        # cgset 027cgset1
        Cgroup.set(config, cgname=CGNAME1)
    except RunError as re:
        if 'cgset: no name-value pair was set' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#3 Expected 'no name-value pair' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#3 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #3 erroneously passed'
        return result, cause

    try:
        # cgset - no flags provided
        Cgroup.set(config)
    except RunError as re:
        if 'Usage is' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#4 Expected 'Usage is' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#4 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #4 erroneously passed'
        return result, cause

    try:
        # cgset -r cpu.shares= 027cgset1
        Cgroup.set(config, cgname=CGNAME1, setting='cpu.shares', value='')
    except RunError as re:
        if 'wrong parameter of option -r' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "#5 Expected 'Wrong parameter of option' to be in stderr"
            return result, cause

        if re.ret != 129:
            result = consts.TEST_FAILED
            cause = (
                        '#5 Expected return code of 129 but received {}'
                        ''.format(re.ret)
                    )
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case #5 erroneously passed'
        return result, cause

    # cgset -h
    ret = Cgroup.set(config, cghelp=True)
    if 'Usage:' not in ret:
        result = consts.TEST_FAILED
        cause = '#6 Failed to print help text'

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
