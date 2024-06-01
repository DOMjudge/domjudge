#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Create a systemd scope
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from cgroup import CgroupVersion
from libcgroup import Cgroup
from systemd import Systemd
from process import Process
from run import RunError
import ftests
import consts
import sys
import os

SLICE = 'libcgtests.slice'
SCOPE = '049delegated.scope'

# Which controller isn't all that important, but it is important that we
# have a cgroup v2 controller
CONTROLLER = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires cgroup v2'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    Cgroup.create_scope(scope_name=SCOPE, slice_name=SLICE)

    if not Systemd.is_delegated(config, SCOPE):
        result = consts.TEST_FAILED
        cause = 'Cgroup is not delegated'

    return result, cause


def teardown(config, result):
    pid = CgroupCli.get(config, cgname=os.path.join(SLICE, SCOPE), setting='cgroup.procs',
                        print_headers=False, values_only=True)
    Process.kill(config, pid)

    if result != consts.TEST_PASSED:
        # Something went wrong.  Let's force the removal of the cgroups just to be safe.
        # Note that this should remove the cgroup, but it won't remove it from systemd's
        # internal caches, so the system may not return to its 'pristine' prior-to-this-test
        # state
        try:
            CgroupCli.delete(config, None, os.path.join(SLICE, SCOPE))
        except RunError:
            pass
    else:
        # There is no need to remove the scope.  systemd should automatically remove it
        # once there are no processes inside of it
        pass

    return consts.TEST_PASSED, None


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
        result = consts.TEST_FAILED
        setup(config)
        [result, cause] = test(config)
    finally:
        teardown(config, result)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
