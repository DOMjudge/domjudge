#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Multithreaded cgroup v2 test
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
from process import Process
import consts
import ftests
import sys
import os

CONTROLLER = 'cpu'
PARENT_CGNAME = '036threaded'
CHILD_CGPATH = os.path.join(PARENT_CGNAME, 'childcg')

SETTING = 'cgroup.type'
AFTER = 'threaded'
THREADS = 3


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'
        return result, cause

    if CgroupVersion.get_version(CONTROLLER) != CgroupVersion.CGROUP_V2:
        result = consts.TEST_SKIPPED
        cause = 'This test requires the cgroup v2'

    return result, cause


def setup(config):
    Cgroup.create(config, CONTROLLER, PARENT_CGNAME)
    Cgroup.create(config, CONTROLLER, CHILD_CGPATH)

    Cgroup.set_and_validate(config, CHILD_CGPATH, SETTING, AFTER)


def test(config):
    config.process.create_threaded_process_in_cgroup(
                                config, CONTROLLER, PARENT_CGNAME, THREADS)

    threads = Cgroup.get(config, controller=None, cgname=PARENT_CGNAME,
                         setting='cgroup.threads', print_headers=False,
                         values_only=True)
    threads = threads.replace('\n', '').split('\t')

#   #pick the first thread
    thread_tid = threads[1]

    Cgroup.set_and_validate(config, CHILD_CGPATH, 'cgroup.threads', thread_tid)

    return consts.TEST_PASSED, None


def teardown(config):
    # destroy the child processes
    pids = Cgroup.get_pids_in_cgroup(config, PARENT_CGNAME, CONTROLLER)
    Process.kill(config, pids)

    Cgroup.delete(config, CONTROLLER, PARENT_CGNAME, recursive=True)


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
