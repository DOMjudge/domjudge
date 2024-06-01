#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Attach a task to a cgroup via cgroup_attach_task()
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup, Version
from cgroup import CgroupVersion
import ftests
import consts
import sys
import os

CGNAME = '052cgattachcg'

# Which controller isn't all that important, but it is important that we
# have a cgroup v2 controller
CONTROLLER = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.add_controller(CONTROLLER)
    cg.create()


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.add_controller(CONTROLLER)
    cg.attach()

    found = False
    pids = CgroupCli.get_pids_in_cgroup(config, CGNAME, CONTROLLER)
    for pid in pids:
        if pid == os.getpid():
            # our process was successfully added to the cgroup
            found = True

    if not found:
        result = consts.TEST_FAILED
        cause = 'Could not find pid {} in cgroup {}'.format(os.getpid(), CGNAME)
        return result, cause

    # now let's attach this process to the root cgroup and ensure we no longer
    # are in CGNAME
    cg.attach(root_cgroup=True)

    found = False
    pids = CgroupCli.get_pids_in_cgroup(config, CGNAME, CONTROLLER)
    for pid in pids:
        if pid == os.getpid():
            # our process was successfully added to the cgroup
            found = True

    if found:
        result = consts.TEST_FAILED
        cause = 'pid {} was erroneously found in cgroup {}'.format(os.getpid(), CGNAME)

    return result, cause


def teardown(config, result):
    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    try:
        cg.delete()
    except RuntimeError:
        pass


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
