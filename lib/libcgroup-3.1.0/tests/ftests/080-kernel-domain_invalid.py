#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to exercise that libcgroup properly handles cgroups that are marked as "domain invalid"
#
# Copyright (c) 2023 Oracle and/or its affiliates
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as Cgroup, Mode
from process import Process
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
PARENTCG = '080domaininvalid'
CHILDCG = os.path.join(PARENTCG, 'childcg')
GRANDCHILDCG = os.path.join(CHILDCG, 'grandchildcg')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if Cgroup.get_cgroup_mode(config) != Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the unified cgroup v2 hierarchy'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, PARENTCG)
    Cgroup.create(config, CONTROLLER, CHILDCG)

    config.process.create_process_in_cgroup(config, CONTROLLER, PARENTCG, ignore_systemd=True)


def test(config):
    #
    # test 1 - ensure that we can create and delete a cgroup (with no controllers) under a
    # parent whose type is 'domain invalid'
    #
    Cgroup.create_and_validate(config, None, GRANDCHILDCG)
    Cgroup.delete(config, None, GRANDCHILDCG)

    #
    # test 2 - attempt to add a process to the child cgroup.  the kernel should not allow this,
    # and libcgroup needs to handle this
    #
    pid = config.process.create_process(config)
    try:
        Cgroup.classify(config, CONTROLLER, CHILDCG, pid)
    except RunError as re:
        if 'Operation not supported' not in re.stderr:
            raise re
    finally:
        Process.kill(config, pid)

    #
    # test 3 - attempt to enable a controller that enforces the no-processes-in-non-leaf-nodes
    # rule.  the kernel should not allow this
    #
    try:
        Cgroup.subtree_control(config, CHILDCG, 'memory')
    except RunError as re:
        if 'No such file or directory' not in re.stderr:
            raise re

    return consts.TEST_PASSED, None


def teardown(config):
    pids = Cgroup.get_pids_in_cgroup(config, PARENTCG, CONTROLLER)
    Process.kill(config, pids)

    Cgroup.delete(config, CONTROLLER, CHILDCG)
    Cgroup.delete(config, CONTROLLER, PARENTCG)


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
