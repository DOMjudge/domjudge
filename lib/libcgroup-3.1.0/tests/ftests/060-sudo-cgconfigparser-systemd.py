#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Advanced cgconfigparser functionality test - systemd configurations
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>

from systemd import Systemd
from process import Process
from cgroup import Cgroup
from run import RunError
import consts
import ftests
import time
import sys
import os

CONTROLLER = 'cpu'
SYSTEMD_CGNAME = '060_cg_in_scope'
OTHER_CGNAME = '060_cg_not_in_scope'

SLICE = 'libcgtests.slice'
SCOPE = 'test060.scope'

CONFIG_FILE_NAME = os.path.join(os.getcwd(), '060cgconfig.conf')

CONFIGURATIONS = [
        # [ 'systemd configuration file', 'Excepted error substring']
        ['systemd {\n}', 'Error: failed to parse file'],

        ['systemd {\n\tslice = libcgroup;\n}',
            'Error: Invalid systemd configuration slice value libcgroup'],

        ['systemd {\n\tscope = test060;\n}',
            'Error: Invalid systemd configuration scope value test060'],

        ['systemd {\n\tslice = libcgroup.slice;\n}',
            'Error: Invalid systemd setting, missing scope name'],

        ['systemd {\n\tscope = test060.scope;\n}',
            'Error: Invalid systemd setting, missing slice name'],

        ['systemd {\n\tsetdefault = yes;\n}',
            'Error: Invalid systemd setting, missing slice name'],

        ['systemd {\n\tpid = 123;\n}',
            'Error: Invalid systemd setting, missing slice name'],

        ['systemd {\n\tInvalid = Invalid;\n}',
            'Error: Invalid systemd configuration Invalid'],

        ['systemd {\n\tslice = libcgroup.slice;\n\tsetdefault = yes;\n\t}',
            'Error: Invalid systemd setting, missing scope name'],

        ['systemd {\n\tscope = test060.scope;\n\tsetdefault = yes;\n\t}',
            'Error: Invalid systemd setting, missing slice name'],

        ['systemd {\n\tslice = libcgroup.slice;\n\tscope = test060.scope;\n\t'
            'setdefault = invalid;\n\t}',
            'Error: Invalid systemd configuration setdefault'],

        ['systemd {\n\tslice = libcgroup.slice;\n\tscope = test060.scope;\n\t'
            'setdefault = yes;\n\tpid = abc;\n}',
            'Error: Invalid systemd configuration pid'],
]


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    pass


def write_conf_file(config, configurations):
    f = open(CONFIG_FILE_NAME, 'w')
    f.write(configurations)
    f.close()


def test_invalid_configurations(config):
    result = consts.TEST_PASSED
    cause = None

    # Try parsing invalid systemd configurations from CONFIGURATION table
    # and none of them is excepted to pass.
    for configuration in CONFIGURATIONS:
        write_conf_file(config, configuration[0])

        try:
            Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)
        except RunError as re:
            if configuration[1] not in re.stdout:
                result = consts.TEST_FAILED
                tmp_cause = (
                            'Unexpected error {}, while parsing configuration:'
                            '\n{}'.format(re.stdout, configuration[0])
                        )
                cause = '\n'.join(filter(None, [cause, tmp_cause]))
        else:
            result = consts.TEST_FAILED
            tmp_cause = (
                        'Creation of systemd default slice/scope, erroneously succeeded with'
                        'configuration:\n{}'.format(configuration[0])
                    )
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause


def test(config):
    result = consts.TEST_PASSED
    cause = None

    result, cause = test_invalid_configurations(config)
    if result == consts.TEST_FAILED:
        return result, cause

    pid = Systemd.write_config_with_pid(config, CONFIG_FILE_NAME, SLICE, SCOPE)

    # Pass a valid configuration to the parser
    Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)

    if not Cgroup.exists(config, CONTROLLER, os.path.join(SLICE, SCOPE), ignore_systemd=True):
        result = consts.TEST_FAILED
        cause = 'Failed to create systemd slice/scope'
        return result, cause

    # It's invalid to pass the same configuration file twice. The values
    # were already read and slice/scope cgroups were created, unless
    # something has gone wrong, this should fail.
    try:
        Cgroup.configparser(config, load_file=CONFIG_FILE_NAME)
    except RunError as re:
        if 'already exists' not in re.stdout:
            result = consts.TEST_FAILED
            cause = 'Unexpected error  {}'.format(re.stdout)
    else:
        result = consts.TEST_FAILED
        cause = 'Creation of systemd default slice/scope erroneously succeeded'

    # killing the pid should remove the scope cgroup too.
    Process.kill(config, pid)

    # Let's pause and wait for the systemd to remove the scope.
    time.sleep(1)

    if Cgroup.exists(config, CONTROLLER, os.path.join(SLICE, SCOPE), ignore_systemd=True):
        result = consts.TEST_FAILED
        cause = 'Systemd failed to remove the scope {}'.format(SCOPE)

    return result, cause


def teardown(config):
    # The scope is already removed, when the task was killed.
    try:
        Systemd.remove_scope_slice_conf(config, SLICE, SCOPE, CONTROLLER, CONFIG_FILE_NAME)
    except RunError as re:
        if 'scope not loaded' not in re.stderr:
            raise re


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    try:
        result = consts.TEST_FAILED
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
