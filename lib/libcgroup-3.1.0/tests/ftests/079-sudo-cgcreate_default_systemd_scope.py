#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to create a default systemd scope using cgcreate
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup
from process import Process
from libcgroup import Mode
from run import RunError
from log import Log
import consts
import ftests
import sys
import os

CONTROLLERS = ['cpu', 'pids']
SLICE = 'libcgroup.slice'
SCOPE_CGNAME = os.path.join(SLICE, '079cgcreate.scope')
CHILD_CGNAME = 'childcg'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if Cgroup.get_cgroup_mode(config) != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup hierarchy'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.create_and_validate(config, CONTROLLERS, SCOPE_CGNAME, create_scope=True,
                               set_default_scope=True)

    # get the placeholder PID that libcgroup placed in the scope
    try:
        pid = int(Cgroup.get(config, None, SCOPE_CGNAME, setting='cgroup.procs',
                             print_headers=False, values_only=True, ignore_systemd=True))
        # use the pid variable so that lint is happy
        Log.log_debug('Cgroup {} has pid {}'.format(SCOPE_CGNAME, pid))
    except RunError:
        result = consts.TEST_FAILED
        cause = 'Failed to read pid in {}\'s cgroup.procs'.format(SCOPE_CGNAME)
        return result, cause

    Cgroup.create_and_validate(config, None, CHILD_CGNAME)

    return result, cause


def teardown(config):
    Cgroup.delete(config, None, CHILD_CGNAME)

    pid = int(Cgroup.get(config, None, SCOPE_CGNAME, setting='cgroup.procs',
                         print_headers=False, values_only=True, ignore_systemd=True))
    Process.kill(config, pid)

    # systemd will automatically remove the cgroup once there are no more pids in
    # the cgroup, so we don't need to delete CGNAME.  But let's try to remove the
    # slice
    try:
        Cgroup.delete(config, CONTROLLERS, SLICE)
    except RunError:
        pass


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
