#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgroup_get_current_controller_path() test using the python bindings (cgroup v1)
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup as CgroupCli, Mode
from libcgroup import Cgroup, Version
from process import Process
import consts
import ftests
import sys
import os


CGNAME = '081getctrlpathv1'
CONTROLLER = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if Cgroup.cgroup_mode() == Mode.CGROUP_MODE_UNIFIED:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the legacy cgroup v1 hierarchy'

    return result, cause


def setup(config):
    CgroupCli.create(config, CONTROLLER, CGNAME)

    config.process.create_process_in_cgroup(config, CONTROLLER, CGNAME, ignore_systemd=True)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    expected_path = "/" + CGNAME
    pid = CgroupCli.get_pids_in_cgroup(config, CGNAME, CONTROLLER)[0]

    cgrp = Cgroup(CGNAME, Version.CGROUP_V2)
    #
    # Test 1 - get the relative path of cgroup, for the pid's cpu controller.
    #          It's expected to pass because we had created cgroup on cpu
    #          hierarchy and moved the task to that group.
    #
    cgrp_path = cgrp.get_current_controller_path(pid, CONTROLLER)
    if cgrp_path != expected_path:
        result = consts.TEST_FAILED
        cause = 'Expected cgroup path {} got {}'.format(expected_path, cgrp_path)

    #
    # Test 2 - get the relative path of cgroup, for the pid's memory controller.
    #          It's expected to fail because we not had created cgroup.
    #
    cgrp_path = cgrp.get_current_controller_path(pid, "memory")
    if cgrp_path == expected_path:
        result = consts.TEST_FAILED
        tmp_cause = 'cgroup path unexpectedly formed {}'.format(cgrp_path)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 3 - get the relative path of cgroup, for the pid's invalid controller.
    #          It's expected to fail because such controller doesn't exists.
    #
    try:
        cgrp_path = cgrp.get_current_controller_path(pid, "invalid")
    except RuntimeError as re:
        if '50011' not in str(re):
            raise re

    #
    # Test 4 - get the relative path of cgroup, for the pid's pass NULL as
    #          controller. It's expected to fail because it's not supported
    #          cgroup v1.
    #
    try:
        cgrp_path = cgrp.get_current_controller_path(pid, None)
    except RuntimeError as re:
        if '50016' not in str(re):
            raise re

    #
    # Test 5 - get the relative path of cgroup, for the pid's pass int as
    #          controller. It's expected to fail because string is expected
    #          for the controller name.
    #
    try:
        cgrp_path = cgrp.get_current_controller_path(pid, 1234)
    except TypeError as re:
        if 'expected controller type string, but passed' not in str(re):
            raise re

    return result, cause


def teardown(config):
    pid = CgroupCli.get_pids_in_cgroup(config, CGNAME, CONTROLLER)[0]
    Process.kill(config, pid)

    CgroupCli.delete(config, CONTROLLER, CGNAME)


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
