#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Cgxget test with no mappable settings
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
CGNAME = '042cgxget'
SETTING = 'cpu.stat'


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version(CONTROLLER) == CgroupVersion.CGROUP_V1:
        # request the opposite version of what this system is running
        requested_ver = CgroupVersion.CGROUP_V2
    else:
        requested_ver = CgroupVersion.CGROUP_V1

    out = Cgroup.xget(
                        config, cgname=CGNAME, setting=SETTING,
                        version=requested_ver, print_headers=False,
                        ignore_unmappable=True
                      )
    if len(out):
        result = consts.TEST_FAILED
        cause = 'Expected cgxget to return nothing.  Received {}'.format(out)

    return result, cause


def teardown(config):
    Cgroup.delete(config, CONTROLLER, CGNAME)


def main(config):
    prereqs(config)
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
