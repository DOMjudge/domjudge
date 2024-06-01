#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to replace idle_thread of scope unit using cgexec -r
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from multiprocessing import active_children
from process import Process
from cgroup import Cgroup
from run import RunError
import consts
import ftests
import time
import sys
import os

CONTROLLER = 'cpu'
SLICE = 'libcgroup.slice'
CGNAME = os.path.join(SLICE, '085cgcreate.scope')
OUT_OF_SCOPE_CGNAME1 = '085outofscope'
OUT_OF_SCOPE_CGNAME2 = '085outofscope.scope'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.create_and_validate(config, CONTROLLER, CGNAME, create_scope=True)

    #
    # Test 1: call cgexec -r to replace idle_thread with infinite while loop
    #
    idle_pid = Cgroup.get_pids_in_cgroup(config, CGNAME, CONTROLLER)[0]
    config.process.create_process_in_cgroup(config, CONTROLLER, CGNAME, cgclassify=False,
                                            replace_idle=True)

    # We need pause, before the cgroups.procs gets updated, post cgexec
    time.sleep(1)

    pid = Cgroup.get_pids_in_cgroup(config, CGNAME, CONTROLLER)[0]
    if idle_pid == pid:
        result = consts.TEST_FAILED
        cause = 'Failed to replace scope idle_thread pid {} {}'.format(idle_pid, pid)
        return result, cause

    #
    # Test 2: call cgexec -r to replace non-idle_thread (while loop,
    #         previously created) with another while loop. It's expected to fail,
    #         because only idle_thread is allowed to be replaced.
    #
    config.process.create_process_in_cgroup(config, CONTROLLER, CGNAME,
                                            cgclassify=False, replace_idle=True)

    # We need pause, before the cgroups.procs gets updated, post cgexec
    time.sleep(1)

    replace_pid = Cgroup.get_pids_in_cgroup(config, CGNAME, CONTROLLER)[0]
    if replace_pid != pid:
        result = consts.TEST_FAILED
        cause = ('Erroneously replaced scope non idle_thread pid {} with '
                 'pid {}'.format(pid, replace_pid))
        return result, cause

    #
    # Test 3: create a non-scope cgroup, try creating a task with replace_idle
    #         set. It should fail way before creating a task, during cgroup name
    #         check.
    Cgroup.create_and_validate(config, CONTROLLER, OUT_OF_SCOPE_CGNAME1)
    config.process.create_process_in_cgroup(config, CONTROLLER, OUT_OF_SCOPE_CGNAME1,
                                            cgclassify=False, replace_idle=True)

    # We need pause, before the cgroups.procs gets updated, post cgexec
    time.sleep(1)

    pid = Cgroup.get_pids_in_cgroup(config, OUT_OF_SCOPE_CGNAME1, CONTROLLER)
    if len(pid) != 0:
        result = consts.TEST_FAILED
        cause = ('Erroneously succeeded in creating task in non scope cgroup: '
                 'pid {}'.format(pid))
        return result, cause

    #
    # Test 4: create a non-scope cgroup with .scope suffix, try creating a task with
    #         replace_idle set. It should fail when trying to find idle_task
    #         check.
    #
    Cgroup.create_and_validate(config, CONTROLLER, OUT_OF_SCOPE_CGNAME2)
    config.process.create_process_in_cgroup(config, CONTROLLER, OUT_OF_SCOPE_CGNAME2,
                                            cgclassify=False, replace_idle=True)

    # We need pause, before the cgroups.procs gets updated, post cgexec
    time.sleep(1)

    pid = Cgroup.get_pids_in_cgroup(config, OUT_OF_SCOPE_CGNAME2, CONTROLLER)
    if len(pid) != 0:
        result = consts.TEST_FAILED
        cause = ('Erroneously succeeded in creating task in non scope cgroup: '
                 'pid {}'.format(pid))

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, OUT_OF_SCOPE_CGNAME1)
    Cgroup.delete(config, CONTROLLER, OUT_OF_SCOPE_CGNAME2)

    pid = Cgroup.get_pids_in_cgroup(config, CGNAME, CONTROLLER)
    Process.kill(config, pid)

    # kill the process, that was created by us but failed to migrate to the cgroup
    active = active_children()
    active[0].terminate()

    # systemd will automatically remove the cgroup once there are no more pids in
    # the cgroup, so we don't need to delete CGNAME.  But let's try to remove the
    # slice
    try:
        Cgroup.delete(config, CONTROLLER, SLICE)
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
