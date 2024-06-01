#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to create a systemd scope with pid using cgcreate
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup
from process import Process
from libcgroup import Mode
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLERS = ['cpu', 'pids']
SLICE = 'libcgroup.slice'
CGNAME1 = os.path.join(SLICE, '084cgcreate1.scope')
CGNAME2 = os.path.join(SLICE, '084cgcreate2.scope')


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

    #
    # Test 1: Pass invalid task pid as scope_pid and systemd scope creation
    #         should fail
    #
    try:
        Cgroup.create_and_validate(config, CONTROLLERS, CGNAME1, create_scope=True,
                                   scope_pid=1000000)
    except RunError as re:
        if 'Process with ID 1000000 does not exist' not in str(re):
            raise re

    #
    # Test 2: Pass a valid task pid as scope_pid and systemd scope creation
    #         will succeed. Read the scope cgroup.procs to find if the task
    #         passed by us was used as scope task.
    #
    scope_pid = config.process.create_process(config)
    Cgroup.create_and_validate(config, CONTROLLERS, CGNAME1, create_scope=True, scope_pid=scope_pid)

    try:
        pid = Cgroup.get_pids_in_cgroup(config, CGNAME1, CONTROLLERS[0])[0]
        if scope_pid != pid:
            result = consts.TEST_FAILED
            cause = 'scope created with other pid {}, expected pid {}'.format(pid, scope_pid)
            return result, cause
    except RunError:
        result = consts.TEST_FAILED
        cause = 'Failed to read pid in {}\'s cgroup.procs'.format(CGNAME1)
        return result, cause

    #
    # Test 3: Pass the already created task pid as scope_pid for a new systemd
    #         scope.  This would mean the CGNAME1 should be killed and the pid
    #         should be the default scope task of CGNAME2
    Cgroup.create_and_validate(config, CONTROLLERS, CGNAME2, create_scope=True, scope_pid=scope_pid)

    # CGNAME1 should be deleted by the systemd.
    try:
        pid = Cgroup.get_pids_in_cgroup(config, CGNAME1, CONTROLLERS[0])[0]
    except RunError as re:
        if 'No such file or directory' not in re.stderr:
            raise re
    else:
        result = consts.TEST_FAILED
        cause = 'Erroneously succeeded reading cgroup.procs in {}'.format(CGNAME1)
        return result, cause

    try:
        pid = Cgroup.get_pids_in_cgroup(config, CGNAME2, CONTROLLERS[0])[0]
        if scope_pid != pid:
            result = consts.TEST_FAILED
            cause = 'scope created with other pid {}, expected pid {}'.format(pid, scope_pid)
            return result, cause
    except RunError:
        result = consts.TEST_FAILED
        cause = 'Failed to read pid in {}\'s cgroup.procs'.format(CGNAME2)

    return result, cause


def teardown(config):
    pid = Cgroup.get_pids_in_cgroup(config, CGNAME2, CONTROLLERS[0])[0]
    Process.kill(config, pid)

    # systemd will automatically remove the cgroup once there are no more pids in
    # the cgroup, so we don't need to delete CGNAME2.  But let's try to remove the
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
