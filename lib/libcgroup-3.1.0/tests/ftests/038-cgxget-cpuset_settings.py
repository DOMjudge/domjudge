#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgxget functionality test - various cpuset settings
#
# Copyright (c) 2021-2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from run import Run, RunError
import consts
import ftests
import sys
import os

CONTROLLER = 'cpuset'
CGNAME = '038cgxget'

CGRP_VER_V1 = CgroupVersion.CGROUP_V1
CGRP_VER_V2 = CgroupVersion.CGROUP_V2

TABLE = [
    # writesetting, writeval, writever, readsetting, readval, readver
    ['cpuset.cpus',           '0-1', CGRP_VER_V1,
     'cpuset.cpus',           '0-1', CGRP_VER_V1],
    ['cpuset.cpus',           '0-1', CGRP_VER_V1,
     'cpuset.cpus',           '0-1', CGRP_VER_V2],
    ['cpuset.cpus',           '0-1', CGRP_VER_V1,
     'cpuset.effective_cpus', '0-1', CGRP_VER_V1],
    ['cpuset.cpus',           '0-1', CGRP_VER_V1,
     'cpuset.cpus.effective', '0-1', CGRP_VER_V2],
    ['cpuset.cpus',           '1',   CGRP_VER_V2,
     'cpuset.cpus',           '1',   CGRP_VER_V1],
    ['cpuset.cpus',           '1',   CGRP_VER_V2,
     'cpuset.cpus',           '1',   CGRP_VER_V2],

    ['cpuset.mems',           '0', CGRP_VER_V1,
     'cpuset.mems',           '0', CGRP_VER_V1],
    ['cpuset.mems',           '0', CGRP_VER_V1,
     'cpuset.mems',           '0', CGRP_VER_V2],
    ['cpuset.mems',           '0', CGRP_VER_V2,
     'cpuset.mems',           '0', CGRP_VER_V1],
    ['cpuset.mems',           '0', CGRP_VER_V2,
     'cpuset.mems',           '0', CGRP_VER_V2],
    ['cpuset.mems',           '0', CGRP_VER_V1,
     'cpuset.effective_mems', '0', CGRP_VER_V1],
    ['cpuset.mems',           '0', CGRP_VER_V1,
     'cpuset.mems.effective', '0', CGRP_VER_V2],

    ['cpuset.cpu_exclusive',  '1',      CGRP_VER_V1,
     'cpuset.cpu_exclusive',  '1',      CGRP_VER_V1],
    ['cpuset.cpu_exclusive',  '1',      CGRP_VER_V1,
     'cpuset.cpus.partition', 'root',   CGRP_VER_V2],
    ['cpuset.cpu_exclusive',  '0',      CGRP_VER_V1,
     'cpuset.cpu_exclusive',  '0',      CGRP_VER_V1],
    ['cpuset.cpu_exclusive',  '0',      CGRP_VER_V1,
     'cpuset.cpus.partition', 'member', CGRP_VER_V2],

    ['cpuset.cpus.partition', 'root',   CGRP_VER_V2,
     'cpuset.cpu_exclusive',  '1',      CGRP_VER_V1],
    ['cpuset.cpus.partition', 'root',   CGRP_VER_V2,
     'cpuset.cpus.partition', 'root',   CGRP_VER_V2],
    ['cpuset.cpus.partition', 'member', CGRP_VER_V2,
     'cpuset.cpu_exclusive', '0',       CGRP_VER_V1],
    ['cpuset.cpus.partition', 'member', CGRP_VER_V2,
     'cpuset.cpus.partition', 'member', CGRP_VER_V2],
]


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    nproc = Run.run('nproc')
    if int(nproc) < 2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires 2 or more processors'

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    for entry in TABLE:
        try:
            Cgroup.xset(config, cgname=CGNAME, setting=entry[0],
                        value=entry[1], version=entry[2])
        except RunError as re:
            if re.stderr.find('Invalid argument') >= 0:
                # The kernel disallowed this setting, likely due to the many
                # complexities of exclusive cpusets
                continue
            raise re

        out = Cgroup.xget(config, cgname=CGNAME, setting=entry[3],
                          version=entry[5], values_only=True,
                          print_headers=False)
        if out != entry[4]:
            result = consts.TEST_FAILED
            cause = (
                        'After setting {}={}, expected {}={}, but received '
                        '{}={}'
                        ''.format(entry[0], entry[1], entry[3], entry[4],
                                  entry[3], out)
                    )
            return result, cause

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


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
