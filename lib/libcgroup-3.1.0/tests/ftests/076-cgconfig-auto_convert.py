#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgconfigparser auto convert functionality test using a configuration file
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'

CGNAME_V1 = '076cgconfig_v1'
CFS_PERIOD = '100000'
CFS_QUOTA = '50000'
CPU_SHARES = '1024'

CGNAME_V2 = '076cgconfig_v2'
CFS_MAX = '"max 100000"'
CPU_WEIGHT = '50'

CONFIG_FILE = '''
group {} {{
    {} {{
        cpu.cfs_period_us = {};
        cpu.cfs_quota_us = {};
        cpu.shares = {};
    }}
}}
group {} {{
    {} {{
        cpu.max = {};
        cpu.weight = {};
    }}
}}'''.format(
                CGNAME_V1, CONTROLLER, CFS_PERIOD, CFS_QUOTA, CPU_SHARES,
                CGNAME_V2, CONTROLLER, CFS_MAX, CPU_WEIGHT
            )

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '078cgconfig.conf')

TABLE = [
            [CGNAME_V1, 'cpu.weight', CgroupVersion.CGROUP_V2, '100'],
            [CGNAME_V1, 'cpu.max', CgroupVersion.CGROUP_V2, '50000 100000'],
            [CGNAME_V2, 'cpu.shares', CgroupVersion.CGROUP_V1, '512'],
            [CGNAME_V2, 'cpu.cfs_period_us', CgroupVersion.CGROUP_V1, '100000'],
            [CGNAME_V2, 'cpu.cfs_quota_us', CgroupVersion.CGROUP_V1, '-1'],
        ]


def prereqs(config):
    return consts.TEST_PASSED, None


def setup(config):
    f = open(CONFIG_FILE_NAME, 'w')
    f.write(CONFIG_FILE)
    f.close()


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)

    for entry in TABLE:
        out = Cgroup.xget(
                            config, cgname=entry[0], setting=entry[1],
                            version=entry[2], values_only=True, print_headers=False
                         )

        if out != entry[3]:
            result = consts.TEST_FAILED
            tmp_cause = (
                            'Expected {}={}, received {}={} '
                            ''.format(entry[1], entry[3], entry[1], out)
                        )
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME_V1)
    Cgroup.delete(config, CONTROLLER, CGNAME_V2)
    os.remove(CONFIG_FILE_NAME)


def main(config):
    [result, cause] = prereqs(config)

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
