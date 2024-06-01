#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgconfigparser functionality test using a configuration directory
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CPU_CTRL = 'cpu'
MEMORY_CTRL = 'memory'
CGNAME = '018cgconfig'
CFS_PERIOD = '400000'
CFS_QUOTA = '50000'
SHARES = '123'

LIMIT_IN_BYTES = '409600'
SOFT_LIMIT_IN_BYTES = '376832'

CONFIG_FILE = '''group
{} {{
    {} {{
        cpu.cfs_period_us = {};
        cpu.cfs_quota_us = {};
        cpu.shares = {};
    }}
    {} {{
        memory.limit_in_bytes = {};
        memory.soft_limit_in_bytes = {};
    }}
}}'''.format(CGNAME, CPU_CTRL, CFS_PERIOD, CFS_QUOTA, SHARES,
             MEMORY_CTRL, LIMIT_IN_BYTES, SOFT_LIMIT_IN_BYTES)

CONFIG_FILE_DIR = os.path.join(os.getcwd(), '018cgconfig')
CONFIG_FILE_NAME = os.path.join(CONFIG_FILE_DIR, 'cgconfig.conf')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'
        return result, cause

    if CgroupVersion.get_version('memory') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 memory controller'

    return result, cause


def setup(config):
    os.mkdir(CONFIG_FILE_DIR)

    f = open(CONFIG_FILE_NAME, 'w')
    f.write(CONFIG_FILE)
    f.close()


def test(config):
    Cgroup.configparser(config, load_dir=CONFIG_FILE_DIR)

    Cgroup.get_and_validate(config, CGNAME, 'cpu.cfs_period_us', CFS_PERIOD)
    Cgroup.get_and_validate(config, CGNAME, 'cpu.cfs_quota_us', CFS_QUOTA)
    Cgroup.get_and_validate(config, CGNAME, 'cpu.shares', SHARES)
    Cgroup.get_and_validate(config, CGNAME, 'memory.limit_in_bytes',
                            LIMIT_IN_BYTES)
    Cgroup.get_and_validate(config, CGNAME, 'memory.soft_limit_in_bytes',
                            SOFT_LIMIT_IN_BYTES)

    return consts.TEST_PASSED, None


def teardown(config):
    Cgroup.delete(config, CPU_CTRL, CGNAME)
    Cgroup.delete(config, MEMORY_CTRL, CGNAME)
    os.remove(CONFIG_FILE_NAME)
    os.rmdir(CONFIG_FILE_DIR)


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
