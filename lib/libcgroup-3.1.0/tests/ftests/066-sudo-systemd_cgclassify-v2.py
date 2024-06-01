#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgclassify functionality test - '-b' '-g' <controller> (cgroup v2)
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from systemd import Systemd
from process import Process
from run import RunError
import consts
import ftests
import time
import sys
import os


CONTROLLER = 'cpu'
SYSTEMD_CGNAME = '066_cg_in_scope'
OTHER_CGNAME = '066_cg_not_in_scope'

SLICE = 'libcgtests.slice'
SCOPE = 'test066.scope'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '066cgconfig.conf')

SYSTEMD_PIDS = ''
OTHER_PIDS = ''


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
    # a task is attached to test066.scope. Attach the task from test066.scope
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


def create_process_get_pid(config, CGNAME, SLICENAME='', ignore_systemd=False):
    result = consts.TEST_PASSED
    cause = None

    config.process.create_process_in_cgroup(
                                                config, CONTROLLER, CGNAME,
                                                ignore_systemd=ignore_systemd
                                            )

    pids = Cgroup.get_pids_in_cgroup(config, os.path.join(SLICENAME, CGNAME), CONTROLLER)
    if pids is None:
        result = consts.TEST_FAILED
        cause = 'No processes were found in cgroup {}'.format(CGNAME)

    return pids, result, cause


def test(config):
    global SYSTEMD_PIDS, OTHER_PIDS

    result = consts.TEST_PASSED
    cause = None

    # Test cgclassify, that creates a process and then uses cgclassify
    # to migrate the task the cgroup.
    SYSTEMD_PIDS, result, cause = create_process_get_pid(
                                                            config, SYSTEMD_CGNAME,
                                                            os.path.join(SLICE, SCOPE)
                                                        )
    if result == consts.TEST_FAILED:
        return result, cause

    OTHER_PIDS, result, tmp_cause = create_process_get_pid(
                                                                config, OTHER_CGNAME,
                                                                ignore_systemd=True
                                                          )
    if result == consts.TEST_FAILED:
        return result, cause

    # classify a task from the non-systemd scope cgroup (OTHER_CGNAME) to
    # systemd scope cgroup (SYSTEMD_CGNAME).  Migration should fail due to
    # the incorrect destination cgroup path that gets constructed, without
    # the systemd slice/scope when ignore_systemd=True)
    try:
        Cgroup.classify(config, CONTROLLER, SYSTEMD_CGNAME, OTHER_PIDS, ignore_systemd=True)
    except RunError as re:
        err_str = 'Error changing group of pid {}: Cgroup does not exist'.format(OTHER_PIDS[0])
        if re.stderr != err_str:
            raise re
    else:
        result = consts.TEST_FAILED
        cause = 'Changing group of pid {} erroneously succeeded'.format(OTHER_PIDS[0])

    # classify a task from the systemd scope cgroup (SYSTEMD_CGNAME) to
    # non-systemd scope cgroup (OTHER_CGNAME).  Migration should fail due
    # to the incorrect destination cgroup path that gets constructed, with
    # the systemd slice/scope when ignore_systemd=False)
    try:
        Cgroup.classify(config, CONTROLLER, OTHER_CGNAME, SYSTEMD_PIDS[1])
    except RunError as re:
        err_str = 'Error changing group of pid {}: Cgroup does not exist'.format(
                  SYSTEMD_PIDS[1])
        if re.stderr != err_str:
            raise re
    else:
        result = consts.TEST_FAILED
        tmp_cause = 'Changing group of pid {} erroneously succeeded'.format(SYSTEMD_PIDS[1])
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    # classify the task from the non-systemd scope cgroup to systemd scope cgroup.
    Cgroup.classify(config, CONTROLLER, SYSTEMD_CGNAME, OTHER_PIDS)

    return result, cause


def teardown(config):
    global SYSTEMD_PIDS, OTHER_PIDS

    Process.kill(config, SYSTEMD_PIDS)
    Process.kill(config, OTHER_PIDS)

    # We need a pause, so that cgroup.procs gets updated.
    time.sleep(1)

    os.remove(CONFIG_FILE_NAME)

    try:
        Cgroup.delete(config, CONTROLLER, cgname=SLICE, ignore_systemd=True)
    except RunError as re:
        if 'No such file or directory' not in re.stderr:
            raise re

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
