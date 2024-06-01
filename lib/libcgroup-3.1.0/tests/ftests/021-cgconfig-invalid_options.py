#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# cgconfigparser functionality test - invalid and help options
#
# Copyright (c) 2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from cgroup import Cgroup
from run import RunError
import consts
import ftests
import sys
import os

CONTROLLER = 'cpuset'
CGNAME = '021cgconfig'

CONFIG_FILE = '''group
{} {{
    {} {{
        cpuset.cpus = abc123;
    }}
}}'''.format(CGNAME, CONTROLLER)

USER = 'cguser021'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '021cgconfig.conf')


def prereqs(config):
    pass


def setup(config):
    f = open(CONFIG_FILE_NAME, 'w')
    f.write(CONFIG_FILE)
    f.close()


def test(config):
    result = consts.TEST_PASSED
    cause = None

    ret = Cgroup.configparser(config, cghelp=True)
    if 'Parse and load the specified cgroups' not in ret:
        result = consts.TEST_FAILED
        cause = 'Failed to print cgconfigparser help text'
        return result, cause

    try:
        Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)
    except RunError as re:
        if 'Invalid argument' not in re.stderr:
            result = consts.TEST_FAILED
            cause = "Expected 'Invalid argument' to be in stderr"
            return result, cause

        if re.ret != 96:
            result = consts.TEST_FAILED
            cause = 'Expected return code of 96 but received {}'.format(re.ret)
            return result, cause
    else:
        result = consts.TEST_FAILED
        cause = 'Test case erroneously passed'

    return result, cause


def teardown(config):
    os.remove(CONFIG_FILE_NAME)


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
