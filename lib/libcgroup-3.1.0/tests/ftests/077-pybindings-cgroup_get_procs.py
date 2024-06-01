#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgroup_get_procs() test using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup as CgroupCli
from libcgroup import Cgroup, Version
from process import Process
import consts
import ftests
import sys
import os


CGNAME = '077getprocs/cgwithpids'
EMPTY_CGNAME = '077getprocs/cgwithoutpids'
CONTROLLERS = ['cpu', 'pids']
PID_CNT = 20

initial_pid_list = list()


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    global initial_pid_list
    CgroupCli.create(config, CONTROLLERS, CGNAME)
    CgroupCli.create(config, CONTROLLERS, EMPTY_CGNAME)

    for i in range(0, PID_CNT):
        pid = config.process.create_process(config)
        initial_pid_list.append(pid)

    CgroupCli.classify(config, CONTROLLERS, CGNAME, initial_pid_list, ignore_systemd=True)
    initial_pid_list = initial_pid_list.sort()


def test(config):
    global initial_pid_list
    result = consts.TEST_PASSED
    cause = None

    #
    # Test 1 - verify pids are properly populated and retrieved from a cgroup
    #
    cg = Cgroup(CGNAME, Version.CGROUP_V2)
    for controller in CONTROLLERS:
        cg.add_controller(controller)
    pid_list = cg.get_processes().sort()

    if pid_list != initial_pid_list:
        result = consts.TEST_FAILED
        tmp_cause = 'The pid lists do not match\n{}\n{}'.format(initial_pid_list, pid_list)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 2 - verify there are no pids in the empty cgroup
    #
    emptycg = Cgroup(EMPTY_CGNAME, Version.CGROUP_V2)
    for controller in CONTROLLERS:
        emptycg.add_controller(controller)
    empty_pid_list = emptycg.get_processes()

    if len(empty_pid_list) != 0:
        result = consts.TEST_FAILED
        tmp_cause = 'The pid list unexpectedly was populated\n{}'.format(empty_pid_list)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def teardown(config):
    global initial_pid_list

    Process.kill(config, initial_pid_list)
    CgroupCli.delete(config, CONTROLLERS, EMPTY_CGNAME)
    CgroupCli.delete(config, CONTROLLERS, os.path.dirname(CGNAME), recursive=True)


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
