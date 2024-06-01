#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - no flags, only a cgroup
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup
import consts
import ftests
import sys
import os

CONTROLLER = 'cpuset'
CGNAME = '033cgget'


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    out = Cgroup.get(config, controller=None, cgname=CGNAME)

    if out.splitlines()[0] != '{}:'.format(CGNAME):
        result = consts.TEST_FAILED
        cause = (
                    'cgget expected the cgroup name {} in the first line.\n'
                    'Instead it received {}'
                    ''.format(CGNAME, out.splitlines()[0])
                )

    if len(out.splitlines()) < 5:
        result = consts.TEST_FAILED
        cause = (
                    'Too few lines output by cgget.  Received {} lines'
                    ''.format(len(out.splitlines()))
                )

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


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
