#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to set the permissions on a cgroup using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli, CgroupVersion
from libcgroup import Cgroup, Version
import consts
import ftests
import utils
import stat
import sys
import os

CGNAME = '056setperms'
CONTROLLER = 'memory'
# 0711
DIR_MODE = stat.S_IRWXU | stat.S_IXGRP | stat.S_IXOTH
# 0640
CTRL_MODE = stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP
# 0771
TASK_MODE = stat.S_IRWXU | stat.S_IRWXG | stat.S_IXOTH


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    cg.set_permissions(DIR_MODE, CTRL_MODE, TASK_MODE)
    cg.add_controller(CONTROLLER)
    cg.create(ignore_ownership=False)

    dir_path = os.path.join(CgroupCli.get_controller_mount_point(CONTROLLER), CGNAME)

    dir_mode = utils.get_file_permissions(config, dir_path)
    if int(dir_mode, 8) != DIR_MODE:
        result = consts.TEST_FAILED
        cause = 'Expected directory mode to be {} but it\'s {}'.format(
                    format(DIR_MODE, '03o'), dir_mode)

    ctrl_path = os.path.join(CgroupCli.get_controller_mount_point(CONTROLLER), CGNAME,
                             'cgroup.procs')

    ctrl_mode = utils.get_file_permissions(config, ctrl_path)
    if int(ctrl_mode, 8) != CTRL_MODE:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected cgroup.procs mode to be {} but it\'s {}'.format(
                    format(CTRL_MODE, '03o'), ctrl_mode)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    CgroupCli.delete(config, None, CGNAME)


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
