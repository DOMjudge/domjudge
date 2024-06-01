#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgget functionality test - multiple '-g' flags
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup, CgroupVersion
import consts
import ftests
import sys
import os

CONTROLLER1 = 'pids'
CONTROLLER2 = 'cpu'
CGNAME = '013cgget'

EXPECTED_OUT_V1 = [
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.cfs_period_us: 100000
    cpu.stat: nr_periods 0
            nr_throttled 0
            throttled_time 0
    cpu.shares: 1024
    cpu.cfs_quota_us: -1
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max''',
    # with cfs_bandwidth without cpu.stat nr_busts, burst_time
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.cfs_burst_us: 0
    cpu.cfs_period_us: 100000
    cpu.stat: nr_periods 0
            nr_throttled 0
            throttled_time 0
    cpu.shares: 1024
    cpu.idle: 0
    cpu.cfs_quota_us: -1
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max''',
    # with cfs_bandwidth with cpu.stat nr_busts, burst_time
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.cfs_burst_us: 0
    cpu.cfs_period_us: 100000
    cpu.stat: nr_periods 0
            nr_throttled 0
            throttled_time 0
            nr_bursts 0
            burst_time 0
    cpu.shares: 1024
    cpu.idle: 0
    cpu.cfs_quota_us: -1
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max'''
]

EXPECTED_OUT_V2 = [
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.weight: 100
    cpu.stat: usage_usec 0
            user_usec 0
            system_usec 0
            nr_periods 0
            nr_throttled 0
            throttled_usec 0
    cpu.weight.nice: 0
    cpu.pressure: some avg10=0.00 avg60=0.00 avg300=0.00 total=0
    cpu.max: max 100000
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max''',
    # with PSI
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.weight: 100
    cpu.stat: usage_usec 0
            user_usec 0
            system_usec 0
            nr_periods 0
            nr_throttled 0
            throttled_usec 0
    cpu.weight.nice: 0
    cpu.pressure: some avg10=0.00 avg60=0.00 avg300=0.00 total=0
            full avg10=0.00 avg60=0.00 avg300=0.00 total=0
    cpu.max: max 100000
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max''',
    # with PSI, cfs_bandwidth without cpu.stat nr_busts, burst_time
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.weight: 100
    cpu.stat: usage_usec 0
            user_usec 0
            system_usec 0
            nr_periods 0
            nr_throttled 0
            throttled_usec 0
    cpu.weight.nice: 0
    cpu.pressure: some avg10=0.00 avg60=0.00 avg300=0.00 total=0
            full avg10=0.00 avg60=0.00 avg300=0.00 total=0
    cpu.idle: 0
    cpu.max.burst: 0
    cpu.max: max 100000
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max''',
    # with PSI, cfs_bandwidth with cpu.stat nr_busts, burst_time
    '''013cgget:
    pids.current: 0
    pids.events: max 0
    pids.max: max
    cpu.weight: 100
    cpu.stat: usage_usec 0
            user_usec 0
            system_usec 0
            nr_periods 0
            nr_throttled 0
            throttled_usec 0
            nr_bursts 0
            burst_usec 0
    cpu.weight.nice: 0
    cpu.pressure: some avg10=0.00 avg60=0.00 avg300=0.00 total=0
            full avg10=0.00 avg60=0.00 avg300=0.00 total=0
    cpu.idle: 0
    cpu.max.burst: 0
    cpu.max: max 100000
    cpu.uclamp.min: 0.00
    cpu.uclamp.max: max'''
]


def prereqs(config):
    pass


def setup(config):
    Cgroup.create(config, CONTROLLER1, CGNAME)
    Cgroup.create(config, CONTROLLER2, CGNAME)


def test(config):
    result = consts.TEST_PASSED
    cause = None

    out = Cgroup.get(config, controller=[CONTROLLER1, CONTROLLER2],
                     cgname=CGNAME)

    version = CgroupVersion.get_version(CONTROLLER1)

    if version == CgroupVersion.CGROUP_V1:
        for expected_out in EXPECTED_OUT_V1:
            if len(out.splitlines()) == len(expected_out.splitlines()):
                break
    elif version == CgroupVersion.CGROUP_V2:
        for expected_out in EXPECTED_OUT_V2:
            if len(out.splitlines()) == len(expected_out.splitlines()):
                break

    if len(out.splitlines()) != len(expected_out.splitlines()):
        result = consts.TEST_FAILED
        cause = (
                    'Expected {} lines but received {} lines'
                    ''.format(len(expected_out.splitlines()),
                              len(out.splitlines()))
                )
        return result, cause

    for line_num, line in enumerate(out.splitlines()):
        if line.strip() != expected_out.splitlines()[line_num].strip():
            result = consts.TEST_FAILED
            cause = (
                        'Expected line:\n\t{}\nbut received line:\n\t{}'
                        ''.format(expected_out.splitlines()[line_num].strip(),
                                  line.strip())
                    )
            return result, cause

    return result, cause


def teardown(config):
    ver1 = CgroupVersion.get_version(CONTROLLER1)
    ver2 = CgroupVersion.get_version(CONTROLLER2)

    if ver1 == CgroupVersion.CGROUP_V2 and \
       ver2 == CgroupVersion.CGROUP_V2:
        # If both controllers are cgroup v2, then we only need to delete
        # one cgroup.  The path will be the same for both
        Cgroup.delete(config, [CONTROLLER1, CONTROLLER2], CGNAME)
    else:
        Cgroup.delete(config, CONTROLLER1, CGNAME)
        Cgroup.delete(config, CONTROLLER2, CGNAME)


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
