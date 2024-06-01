#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgconfigparser functionality test using a configuration file
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '017cgconfig'
CFS_PERIOD = '500000'
CFS_QUOTA = '100000'
SHARES = '999'

CONFIG_FILE = '''group
{} {{
    {} {{
        cpu.cfs_period_us = {};
        cpu.cfs_quota_us = {};
        cpu.shares = {};
    }}
}}'''.format(CGNAME, CONTROLLER, CFS_PERIOD, CFS_QUOTA, SHARES)

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '017cgconfig.conf')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V1:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v1 cpu controller'

    return result, cause


def setup(config):
    f = open(CONFIG_FILE_NAME, 'w')
    f.write(CONFIG_FILE)
    f.close()


def test(config):
    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)

    Cgroup.get_and_validate(config, CGNAME, 'cpu.cfs_period_us', CFS_PERIOD)
    Cgroup.get_and_validate(config, CGNAME, 'cpu.cfs_quota_us', CFS_QUOTA)
    Cgroup.get_and_validate(config, CGNAME, 'cpu.shares', SHARES)

    return consts.TEST_PASSED, None


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)
    os.remove(CONFIG_FILE_NAME)


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
