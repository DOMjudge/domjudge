#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to move a pid to a cgroup
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from libcgroup import Cgroup, Mode, Version
from process import Process
import consts
import ftests
import sys
import os

CGNAME = '087movepid'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if Cgroup.cgroup_mode() != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup hierarchy'

    return result, cause


def setup(config):
    result = consts.TEST_PASSED
    cause = None

    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.create()

    pid = config.process.create_process(config)

    path = Cgroup.get_current_controller_path(pid)
    if path == '/' + CGNAME:
        result = consts.TEST_FAILED
        cause = 'The PID was already in the destination cgroup {}'.format(CGNAME)

    return result, cause, pid


def test(config, pid):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.move_process(pid, CGNAME)

    path = Cgroup.get_current_controller_path(pid)
    if path != '/' + CGNAME:
        result = consts.TEST_FAILED
        cause = 'Expected the pid to be in {} cgroup, but was instead in {}'.format(CGNAME, path)

    return result, cause


def teardown(config, pid):
    Process.kill(config, pid)

    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.delete()


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    [result, cause, pid] = setup(config)
    if result != consts.TEST_PASSED:
        teardown(config, pid)
        return [result, cause]

    [result, cause] = test(config, pid)
    teardown(config, pid)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
