#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgxget/cgxset functionality test - '-b' '-g' <controller> (cgroup v1)
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from systemd import Systemd
from run import RunError
import consts
import ftests
import sys
import os


CONTROLLER = 'cpu'
SYSTEMD_CGNAME = '069_cg_in_scope'
OTHER_CGNAME = '069_cg_not_in_scope'

SLICE = 'libcgtests.slice'
SCOPE = 'test069.scope'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '069cgconfig.conf')

CGRP_VER_V1 = CgroupVersion.CGROUP_V1
CGRP_VER_V2 = CgroupVersion.CGROUP_V2

TABLE = [
    # writesetting, writeval, writever, readsetting, readval, readver
    ['cpu.shares', '512', CGRP_VER_V1, 'cpu.shares', '512', CGRP_VER_V1],
    ['cpu.shares', '512', CGRP_VER_V1, 'cpu.weight', '50',  CGRP_VER_V2],

    ['cpu.weight', '200', CGRP_VER_V2, 'cpu.shares', '2048', CGRP_VER_V1],
    ['cpu.weight', '200', CGRP_VER_V2, 'cpu.weight', '200',  CGRP_VER_V2],

    ['cpu.cfs_quota_us',  '10000',       CGRP_VER_V1,
     'cpu.cfs_quota_us',  '10000',       CGRP_VER_V1],
    ['cpu.cfs_period_us', '100000',      CGRP_VER_V1,
     'cpu.cfs_period_us', '100000',      CGRP_VER_V1],
    ['cpu.cfs_period_us', '50000',       CGRP_VER_V1,
     'cpu.max',           '10000 50000', CGRP_VER_V2],

    ['cpu.cfs_quota_us',  '-1',         CGRP_VER_V1,
     'cpu.cfs_quota_us',  '-1',         CGRP_VER_V1],
    ['cpu.cfs_period_us', '100000',     CGRP_VER_V1,
     'cpu.max',           'max 100000', CGRP_VER_V2],

    ['cpu.max',           '5000 25000', CGRP_VER_V2,
     'cpu.max',           '5000 25000', CGRP_VER_V2],
    ['cpu.max',           '6000 26000', CGRP_VER_V2,
     'cpu.cfs_quota_us',  '6000',       CGRP_VER_V1],
    ['cpu.max',           '7000 27000', CGRP_VER_V2,
     'cpu.cfs_period_us', '27000',      CGRP_VER_V1],

    ['cpu.max',          'max 40000', CGRP_VER_V2,
     'cpu.max',          'max 40000', CGRP_VER_V2],
    ['cpu.max',          'max 41000', CGRP_VER_V2,
     'cpu.cfs_quota_us', '-1',        CGRP_VER_V1],
]


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

    # create and check if the cgroup was created under the controller sub-tree
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

    cgrps = {SYSTEMD_CGNAME: False, OTHER_CGNAME: True}
    for i in cgrps:
        for entry in TABLE:
            Cgroup.xset(config, cgname=i, setting=entry[0], value=entry[1],
                        version=entry[2], ignore_systemd=cgrps[i])

            out = Cgroup.xget(config, cgname=i, setting=entry[3],
                              version=entry[5], values_only=True,
                              print_headers=False, ignore_systemd=cgrps[i])
            if out != entry[4]:
                result = consts.TEST_FAILED
                tmp_cause = (
                        'After setting {}={}, expected {}={}, but received '
                        '{}={}'.format(entry[0], entry[1], entry[3], entry[4],
                                       entry[3], out)
                        )
                cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    Systemd.remove_scope_slice_conf(config, SLICE, SCOPE, CONTROLLER, CONFIG_FILE_NAME)

    # Incase the error occurs before the creation of OTHER_CGNAME,
    # let's ignore the exception
    try:
        Cgroup.delete(config, CONTROLLER, OTHER_CGNAME, ignore_systemd=True)
    except RunError as re:
        if 'No such file or directory' not in re.stderr:
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
