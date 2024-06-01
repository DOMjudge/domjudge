#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test cgcreate's handling of a failed creation and ensure the directory is deleted
#
# This test is designed to reproduce the steps in
# samples/cmdline/systemd-with-idle-process.md
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from systemd import Systemd
from process import Process
from libcgroup import Mode
from run import RunError
import ftests
import consts
import sys
import os

SLICE = 'libcgtests.slice'
SCOPE = '086example.scope'

CONTROLLER_LIST = ['cpu', 'memory']

TMP_CGNAME = 'tmp'
HIGH_CGNAME = 'high-priority'
MED_CGNAME = 'medium-priority'
LOW_CGNAME = 'low-priority'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupCli.get_cgroup_mode(config) != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup hierarchy'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    #
    # Step 1 in the example
    # sudo cgcreate -c -S -g cpu,memory:mycompany.slice/database.scope
    #
    CgroupCli.create_and_validate(config, CONTROLLER_LIST, os.path.join(SLICE, SCOPE),
                                  create_scope=True, set_default_scope=True)

    if not Systemd.is_delegated(config, SCOPE):
        result = consts.TEST_FAILED
        cause = 'Cgroup is not delegated'
        return result, cause

    #
    # Step 2 in the example
    # $ sudo cgcreate -g cpu,memory:mycompany.slice/database.scope/high-priority
    # cgcreate: can't create cgroup mycompany.slice/database.scope/high-priority: Operation not
    # supported
    #
    try:
        CgroupCli.create_and_validate(config, CONTROLLER_LIST, HIGH_CGNAME)
    except RunError as re:
        if re.ret != 96 or 'No such file or directory' not in re.stderr:
            result = consts.TEST_FAILED
            cause = 'Unexpected error when creating {}: {}'.format(HIGH_CGNAME, re.ret)
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Unexpected success when creating {}'.format(HIGH_CGNAME)
        return result, cause

    #
    # Step 2.i in the example
    # sudo cgset -r cgroup.subtree_control="-cpu -memory" /
    #
    CgroupCli.subtree_control(config, '/', CONTROLLER_LIST, enable=False)

    #
    # Step 2.ii in the example
    # sudo cgcreate -g :tmp
    # sudo cgclassify -g :tmp $(cgget -nv -r cgroup.procs /)
    #
    CgroupCli.create_and_validate(config, None, TMP_CGNAME)
    pids = CgroupCli.get_pids_in_cgroup(config, os.path.join(SLICE, SCOPE), CONTROLLER_LIST[0])
    CgroupCli.classify(config, None, TMP_CGNAME, pids)

    #
    # Step 2.iii in the example
    # sudo cgset -r cgroup.subtree_control="+cpu +memory" /
    #
    CgroupCli.subtree_control(config, '/', CONTROLLER_LIST, enable=True)

    #
    # Step 2 (finally!) in the example
    # sudo cgcreate -g cpu,memory:high-priority -g cpu,memory:medium-priority \
    #               -g cpu,memory:low-priority
    #
    CgroupCli.create_and_validate(config, CONTROLLER_LIST, HIGH_CGNAME)
    CgroupCli.create_and_validate(config, CONTROLLER_LIST, MED_CGNAME)
    CgroupCli.create_and_validate(config, CONTROLLER_LIST, LOW_CGNAME)

    #
    # Step 3.i in the example
    # sudo cgset -r memory.low=1G high-priority
    #
    CgroupCli.set_and_validate(config, HIGH_CGNAME, 'memory.low', '1073741824')

    #
    # Step 3.ii in the example
    # sudo cgset -r memory.max=2G low-priority
    #
    CgroupCli.set_and_validate(config, LOW_CGNAME, 'memory.max', '2147483648')

    #
    # Step 3.iii in the example
    # sudo cgset -r memory.high=3G medium-priority
    #
    CgroupCli.set_and_validate(config, MED_CGNAME, 'memory.high', '3221225472')

    #
    # Step 3.iv in the example
    # sudo cgset -r cpu.weight=600 high-priority
    #
    CgroupCli.set_and_validate(config, HIGH_CGNAME, 'cpu.weight', '600')

    #
    # Step 3.v in the example
    # sudo cgset -r cpu.weight=300 medium-priority
    #
    CgroupCli.set_and_validate(config, MED_CGNAME, 'cpu.weight', '300')

    #
    # Step 3.vi in the example
    # sudo cgset -r cpu.weight=100 low-priority
    #
    CgroupCli.set_and_validate(config, LOW_CGNAME, 'cpu.weight', '100')

    return result, cause


def teardown(config, result):
    pids = CgroupCli.get_pids_in_cgroup(config, os.path.join(SLICE, SCOPE, TMP_CGNAME),
                                        CONTROLLER_LIST[0])
    Process.kill(config, pids)

    if result != consts.TEST_PASSED:
        # Something went wrong.  Let's force the removal of the cgroups just to be safe.
        # Note that this should remove the cgroup, but it won't remove it from systemd's
        # internal caches, so the system may not return to its 'pristine' prior-to-this-test
        # state
        try:
            CgroupCli.delete(config, None, SLICE)
        except RunError:
            pass
    else:
        # There is no need to remove the scope.  systemd should automatically remove it
        # once there are no processes inside of it
        pass

    return consts.TEST_PASSED, None


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
