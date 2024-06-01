#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - '-b' '-g' <controller> (cgroup v2)
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
SYSTEMD_CGNAME = 'cg_in_scope'
OTHER_CGNAME = 'cg_not_in_scope'

SLICE = 'libcgtests.slice'
SCOPE = 'test062.scope'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '062cgconfig.conf')


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if CgroupVersion.get_version('cpu') != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v2 cpu controller'
        return result, cause

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    result = consts.TEST_PASSED
    cause = None

    pid = Systemd.write_config_with_pid(config, CONFIG_FILE_NAME, SLICE, SCOPE)

    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)

    # create and check if the cgroup was created under the systemd default path
    if not Cgroup.create_and_validate(config, None, SYSTEMD_CGNAME):
        result = consts.TEST_FAILED
        cause = (
                    'Failed to create systemd delegated cgroup {} under '
                    '/sys/fs/cgroup/{}/{}/'.format(SYSTEMD_CGNAME, SLICE, SCOPE)
                )
        return result, cause

    # With cgroup v2, we can't enable controller for the child cgroup, while
    # a task is attached to test062.scope. Attach the task from test062.scope
    # to child cgroup SYSTEMD_CGNAME and then enable cpu controller in the parent,
    # so that the cgroup.get() works
    Cgroup.set(config, cgname=SYSTEMD_CGNAME, setting='cgroup.procs', value=pid)

    Cgroup.set(
                config, cgname=(os.path.join(SLICE, SCOPE)), setting='cgroup.subtree_control',
                value='+cpu', ignore_systemd=True
              )

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

    out = Cgroup.get(config, controller=CONTROLLER, cgname=SYSTEMD_CGNAME)
    if len(out.splitlines()) < 10:
        # This cgget command gets all of the settings/values within the cgroup.
        # We don't care about the exact data, but there should be at least 10
        # lines of settings/values
        result = consts.TEST_FAILED
        cause = (
                    'cgget failed to read at least 10 lines from '
                    'cgroup {}: {}'.format(SYSTEMD_CGNAME, out)
                )

    out = Cgroup.get(config, controller=CONTROLLER, cgname=OTHER_CGNAME, ignore_systemd=True)
    if len(out.splitlines()) < 10:
        result = consts.TEST_FAILED
        tmp_cause = (
                        'cgget failed to read at least 10 lines from '
                        'cgroup {}: {}'.format(OTHER_CGNAME, out)
                    )
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    # This should fail because the wrong path should be built up
    out = Cgroup.get(config, controller=CONTROLLER, cgname=SYSTEMD_CGNAME,
                     ignore_systemd=True, print_headers=False)
    if len(out) > 0:
        result = consts.TEST_FAILED
        tmp_cause = (
                        'cgget erroneously read cgroup {} at the wrong '
                        'path: {}'.format(SYSTEMD_CGNAME, out)
                    )
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    # This should fail because the wrong path should be built up
    out = Cgroup.get(config, controller=CONTROLLER, cgname=OTHER_CGNAME, print_headers=False)
    if len(out) > 0:
        result = consts.TEST_FAILED
        cause = (
                    'cgget erroneously read cgroup {} at the wrong '
                    'path: {}'.format(OTHER_CGNAME, out)
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
        if 'No such file or directory' in re.stderr:
            raise re


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    [result, cause] = setup(config)
    if result != consts.TEST_PASSED:
        teardown(config)
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
