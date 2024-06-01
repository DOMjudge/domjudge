#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Basic lssubsys functionality test
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os


def prereqs(config):
    pass


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    mount_list = Cgroup.get_cgroup_mounts(config, expand_v2_mounts=False)

    # cgroup v2 mounts won't show up unless '-a' is specified
    lssubsys_list = Cgroup.lssubsys(config, ls_all=False)

    for mount in mount_list:
        if mount.version == CgroupVersion.CGROUP_V2:
            continue

        if mount.controller == 'name=systemd' or mount.controller == 'systemd':
            continue

        found = False
        for lsmount in lssubsys_list.splitlines():
            if ',' in lsmount:
                for ctrl in lsmount.split(','):
                    if ctrl == mount.controller:
                        found = True
                        break

            if lsmount == mount.controller:
                found = True
                break

        if not found:
            result = consts.TEST_FAILED
            cause = (
                        'Failed to find {} in lssubsys list'
                        ''.format(mount.controller)
                    )
            return result, cause

    return result, cause


def teardown(config):
    pass


def main(config):
    prereqs(config)

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
