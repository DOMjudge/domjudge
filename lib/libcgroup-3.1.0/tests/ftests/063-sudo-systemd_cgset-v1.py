#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgset functionality test - '-b' '-g' <controller> (cgroup v1)
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>

from cgroup import Cgroup, CgroupVersion
from systemd import Systemd
from run import RunError
import consts
import ftests
import sys
import os


CONTROLLER = 'cpu'
SYSTEMD_CGNAME = '063_cg_in_scope'
OTHER_CGNAME = '063_cg_not_in_scope'

SLICE = 'libcgtests.slice'
SCOPE = 'test063.scope'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '063cgconfig.conf')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'
        return result, cause

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    result = consts.TEST_PASSED
    cause = None

    Systemd.write_config_with_pid(config, CONFIG_FILE_NAME, SLICE, SCOPE)

    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)

    # create and check if the cgroup was created under the systemd default path
    if not Cgroup.create_and_validate(config, CONTROLLER, SYSTEMD_CGNAME):
        result = consts.TEST_FAILED
        cause = (
                    'Failed to create systemd delegated cgroup {} under '
                    '/sys/fs/cgroup/{}/{}/{}/'.format(SYSTEMD_CGNAME, CONTROLLER, SLICE, SCOPE)
                )
        return result, cause

    # create and check if the cgroup was created under the controller root
    if not Cgroup.create_and_validate(config, CONTROLLER, OTHER_CGNAME, ignore_systemd=True):
        result = consts.TEST_FAILED
        cause = (
                    'Failed to create cgroup {} under '
                    '/sys/fs/cgroup/{}/'.format(OTHER_CGNAME, CONTROLLER)
                )

    return result, cause


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.set_and_validate(config, SYSTEMD_CGNAME, 'cpu.shares', '200')
    Cgroup.set_and_validate(config, OTHER_CGNAME, 'cpu.shares', '300', ignore_systemd=True)

    try:
        Cgroup.set(config, SYSTEMD_CGNAME, 'cpu.shares', '400', ignore_systemd=True)
    except RunError as re:
        if 'requested group parameter does not exist' not in re.stderr:
            raise re
    else:
        result = consts.TEST_FAILED
        cause = 'Setting cpu.shares on {} erroneously succeeded'.format(SYSTEMD_CGNAME)

    try:
        Cgroup.set(config, OTHER_CGNAME, 'cpu.shares', '500')
    except RunError as re:
        if 'requested group parameter does not exist' not in re.stderr:
            raise re
    else:
        result = consts.TEST_FAILED
        tmp_cause = 'Setting cpu.shares on {} erroneously succeeded'.format(OTHER_CGNAME)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    Systemd.remove_scope_slice_conf(config, SLICE, SCOPE, CONTROLLER, CONFIG_FILE_NAME)

    # Incase the error occurs before the creation of OTHER_CGNAME,
    # let's ignore the exception
    try:
        Cgroup.delete(config, CONTROLLER, OTHER_CGNAME, ignore_systemd=True)
    except RunError as re:
        if 'No such file or directory' in re.stderr:
            raise re


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    [result, cause] = setup(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
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
