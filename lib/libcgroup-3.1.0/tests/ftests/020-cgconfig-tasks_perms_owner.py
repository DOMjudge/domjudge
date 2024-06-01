#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgconfigparser functionality test - '-s', '-t', flags
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from container import ContainerError
from run import Run, RunError
import consts
import ftests
import utils
import sys
import os

CONTROLLER = 'cpuset'
CGNAME = '020cgconfig'

CONFIG_FILE = '''group
{} {{
    {} {{
    }}
}}'''.format(CGNAME, CONTROLLER)

USER = 'cguser020'
GROUP = 'cggroup020'
TPERM = '642'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '020cgconfig.conf')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpuset') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpuset controller'

    return result, cause


def setup(config):
    f = open(CONFIG_FILE_NAME, 'w')
    f.write(CONFIG_FILE)
    f.close()

    if config.args.container:
        config.container.run(['useradd', '-p', 'Test020#1', USER])
        config.container.run(['groupadd', GROUP])
    else:
        Run.run(['sudo', 'useradd', '-p', 'Test020#1', USER])
        Run.run(['sudo', 'groupadd', '-f', GROUP])


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME, tperm=TPERM,
                        tasks_usr=USER, tasks_grp=GROUP)

    mnt_path = Cgroup.get_controller_mount_point(CONTROLLER)
    tasks_path = os.path.join(mnt_path, CGNAME, 'tasks')

    user = utils.get_file_owner_username(config, tasks_path)
    group = utils.get_file_owner_group_name(config, tasks_path)

    if user != USER:
        result = consts.TEST_FAILED
        cause = (
                    'Owner name failed.  Expected {}, received {}\n'
                    ''.format(USER, user)
                )
        return result, cause

    if group != GROUP:
        result = consts.TEST_FAILED
        cause = (
                    'Owner group failed.  Expected {}, received {}\n'
                    ''.format(GROUP, group)
                )
        return result, cause

    tperm = utils.get_file_permissions(config, tasks_path)
    if tperm != TPERM:
        result = consts.TEST_FAILED
        cause = (
                    'File permissions failed.  Expected {}, received {}\n'
                    ''.format(TPERM, tperm)
                )

    return result, cause


def teardown(config):
    os.remove(CONFIG_FILE_NAME)

    try:
        if config.args.container:
            config.container.run(['userdel', USER])
            config.container.run(['groupdel', GROUP])
        else:
            Run.run(['sudo', 'userdel', '-r', USER])
            Run.run(['sudo', 'groupdel', GROUP])
    except (ContainerError, RunError, ValueError):
        pass

    Cgroup.delete(config, CONTROLLER, CGNAME)


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
