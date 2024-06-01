#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Test to set the uid/gid on a cgroup v1 cgroup using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli, CgroupVersion
from libcgroup import Cgroup, Version
import consts
import ftests
import utils
import sys
import os

CGNAME = '055setuidgid'
CONTROLLER = 'cpu'
TASKS_UID = 1234
TASKS_GID = 5678
CTRL_UID = 3456
CTRL_GID = 7890


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v1'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    cg = Cgroup(CGNAME, Version.CGROUP_V1)
    cg.set_uid_gid(TASKS_UID, TASKS_GID, CTRL_UID, CTRL_GID)
    cg.add_controller(CONTROLLER)
    cg.create(ignore_ownership=False)

    tasks_path = os.path.join(CgroupCli.get_controller_mount_point(CONTROLLER), CGNAME,
                              'tasks')

    tasks_uid = utils.get_file_owner_uid(config, tasks_path)
    if tasks_uid != TASKS_UID:
        result = consts.TEST_FAILED
        cause = 'Expected tasks owner to be {} but it\'s {}'.format(TASKS_UID, tasks_uid)

    tasks_gid = utils.get_file_owner_gid(config, tasks_path)
    if tasks_gid != TASKS_GID:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected tasks group to be {} but it\'s {}'.format(TASKS_GID, tasks_gid)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    ctrl_path = os.path.join(CgroupCli.get_controller_mount_point(CONTROLLER), CGNAME,
                             'cgroup.procs')

    ctrl_uid = utils.get_file_owner_uid(config, ctrl_path)
    if ctrl_uid != CTRL_UID:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected cgroup.procs owner to be {} but it\'s {}'.format(CTRL_UID, ctrl_uid)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    ctrl_gid = utils.get_file_owner_gid(config, ctrl_path)
    if ctrl_gid != CTRL_GID:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected cgroup.procs group to be {} but it\'s {}'.format(CTRL_GID, ctrl_gid)
        if not cause:
            cause = tmp_cause
        else:
            cause = '{}\n{}'.format(cause, tmp_cause)

    return result, cause


def teardown(config):
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
