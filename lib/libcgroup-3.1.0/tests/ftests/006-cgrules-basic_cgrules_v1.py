#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Basic cgrules functionality test
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from process import Process
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
PARENT_CGNAME = '006cgrules'
CHILD_CGNAME = 'childcg'

# move all perl processes to the 006cgrules/childcg cgroup in the
# cpu controller
CGRULE = (
            '*:/usr/bin/perl cpu {}'
            ''.format(os.path.join(PARENT_CGNAME, CHILD_CGNAME))
         )

cg = Cgroup(os.path.join(PARENT_CGNAME, CHILD_CGNAME))


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, PARENT_CGNAME)
    Cgroup.create(config, CONTROLLER,
                  os.path.join(PARENT_CGNAME, CHILD_CGNAME))

    Cgroup.set_cgrules_conf(config, CGRULE, append=False)
    cg.start_cgrules(config)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    pid = config.process.create_process(config)
    proc_cgroup = Process.get_cgroup(config, pid, CONTROLLER)

    # proc/{pid}/cgroup alsways prepends a '/' to the cgroup path
    if proc_cgroup != os.path.join('/', PARENT_CGNAME, CHILD_CGNAME):
        result = consts.TEST_FAILED
        cause = (
                    'PID {} was expected to be in cgroup {} but is in '
                    'cgroup {}'
                    ''.format(pid,
                              os.path.join('/', PARENT_CGNAME, CHILD_CGNAME),
                              proc_cgroup)
                )

    return result, cause


def teardown(config):
    # destroy the child processes
    config.process.join_children(config)
    cg.join_children(config)
    Cgroup.delete(config, CONTROLLER, PARENT_CGNAME, recursive=True)


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
        setup(config)
        [result, cause] = test(config)
    finally:
        teardown(config)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
