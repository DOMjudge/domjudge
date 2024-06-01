#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to excerise cgroup_setup_mode() helpers
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup, Mode
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

    if mode1 == Mode.CGROUP_MODE_LEGACY:
        ret = Cgroup.is_cgroup_mode_legacy()
        if ret is False:
            result = consts.TEST_FAILED
            cause = 'mode mismatch: libcgroup mode: legacy (v1) check, returned false'
    elif mode1 == Mode.CGROUP_MODE_HYBRID:
        ret = Cgroup.is_cgroup_mode_hybrid()
        if ret is False:
            result = consts.TEST_FAILED
            cause = 'mode mismatch: libcgroup mode: hybrid (v1/v2) check, returned false'
    elif mode1 == Mode.CGROUP_MODE_UNIFIED:
        ret = Cgroup.is_cgroup_mode_unified()
        if ret is False:
            result = consts.TEST_FAILED
            cause = 'mode mismatch: libcgroup mode: unified (v2) check, returned false'
    else:
        result = consts.TEST_FAILED
        cause = 'Unknown libcgroup mode: {}'.format(mode1)

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
