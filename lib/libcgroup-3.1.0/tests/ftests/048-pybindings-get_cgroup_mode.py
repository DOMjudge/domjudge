#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to get the cgroup mode
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup
import consts
import ftests
import sys
import os


def prereqs(config):
    pass


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    mode1 = Cgroup.cgroup_mode()
    mode2 = CgroupCli.get_cgroup_mode(config)

    if mode1 != mode2:
        result = consts.TEST_FAILED
        cause = 'mode mismatch: libcgroup mode: {}, tests mode: {}'.format(mode1, mode2)

    return result, cause


def teardown(config):
    pass


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
