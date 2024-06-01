#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# set_default_systemd_cgroup functionality test using the python bindings
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from libcgroup import Version, Cgroup, Mode
from cgroup import Cgroup as CgroupCli
from process import Process
import consts
import ftests
import time
import sys
import os


OTHER_CGNAME = '071_cg_not_in_scope'
SLICE = 'libcgtests.slice'
SCOPE = 'test071.scope'

CONTROLLER = 'cpu'


def prereqs(config):
    result = consts.TEST_PASSED
    cause = None

    if config.args.container:
        result = consts.TEST_SKIPPED
        cause = 'This test cannot be run within a container'

    return result, cause


def setup(config):
    pass


def test(config):
    result = consts.TEST_PASSED
    cause = None

    #
    # Test 1 - Ensure _set_default_systemd_cgroup() throws an exception if
    #          libcgroup doesn't set a default (slice/scope) cgroup path
    #
    try:
        Cgroup._set_default_systemd_cgroup()
    except RuntimeError as re:
        if 'Failed to set' not in str(re):
            result = consts.TEST_FAILED
            cause = 'Expected \'Failed to set\' to be in the exception, ' \
                    'received {}'.format(str(re))
    else:
        result = consts.TEST_FAILED
        cause = '_set_default_systemd_cgroup() erroneously passed'

    #
    # Test 2 - write_default_systemd_scope() should succeed if the slice/scope
    #          are invalid and we're not setting it as the default.  If we
    #          create a cgroup at this point, it should be created at the root
    #          cgroup level, and the default slice/scope should have no bearing
    #
    Cgroup.write_default_systemd_scope(SLICE, SCOPE, False)

    pid = config.process.create_process(config)
    cg = Cgroup(OTHER_CGNAME, Version.CGROUP_V2)
    cg.add_controller(CONTROLLER)
    cg.create()
    Cgroup.move_process(pid, OTHER_CGNAME, CONTROLLER)
    cg_pid = cg.get_processes()[0]

    if pid != cg_pid:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected pid {} to be in {} cgroup, but received pid {} ' \
                    'via python bindings instead'.format(pid, OTHER_CGNAME, cg_pid)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    cli_pid = CgroupCli.get_pids_in_cgroup(config, OTHER_CGNAME, CONTROLLER)[0]

    if pid != cli_pid:
        result = consts.TEST_FAILED
        tmp_cause = 'Expected pid {} to be in {} cgroup, but received pid {} ' \
                    'via CLI instead'.format(pid, OTHER_CGNAME, cli_pid)
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    Process.kill(config, pid)
    cg.delete()

    #
    # Test 3 - Write the slice/scope and attempt to set them as the default.
    #          This should fail because they haven't been created yet, and thus
    #          it's an invalid path
    #
    try:
        Cgroup.write_default_systemd_scope(SLICE, SCOPE, True)
    except RuntimeError as re:
        if 'Failed to set' not in str(re):
            result = consts.TEST_FAILED
            tmp_cause = 'Expected \'Failed to set\' to be in the exception, ' \
                        'received {}'.format(str(re))
            cause = '\n'.join(filter(None, [cause, tmp_cause]))
    else:
        result = consts.TEST_FAILED
        tmp_cause = 'write_default_systemd_scope() erroneously passed'
        cause = '\n'.join(filter(None, [cause, tmp_cause]))

    #
    # Test 4 - Create a systemd scope and set it as the default.  Everything
    #          should work properly in this case
    #
    pid = None
    if Cgroup.cgroup_mode() != Mode.CGROUP_MODE_LEGACY:
        pid = config.process.create_process(config)
        Cgroup.create_scope(SCOPE, SLICE, pid=pid)
        Cgroup.write_default_systemd_scope(SLICE, SCOPE)

        cg = Cgroup('/', Version.CGROUP_V2)
        cg_pid = cg.get_processes()[0]

        if pid != cg_pid:
            result = consts.TEST_FAILED
            tmp_cause = 'Expected pid {} to be in \'/\' cgroup, but received pid {} ' \
                        'instead'.format(pid, cg_pid)
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

        path = Cgroup.get_current_controller_path(pid)
        if path != os.path.join('/', SLICE, SCOPE):
            result = consts.TEST_FAILED
            tmp_cause = 'Expected pid path to be: {}, but received path {} ' \
                        'instead'.format(os.path.join('/', SLICE, SCOPE), path)
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

        cli_pid = CgroupCli.get_pids_in_cgroup(config, os.path.join(SLICE, SCOPE), CONTROLLER)[0]

        if pid != cli_pid:
            result = consts.TEST_FAILED
            tmp_cause = 'Expected pid {} to be in {} cgroup, but received pid {} ' \
                        'via CLI instead'.format(pid, os.path.join(SLICE, SCOPE), cli_pid)
            cause = '\n'.join(filter(None, [cause, tmp_cause]))

    return result, cause, pid


def teardown(config, pid):
    if pid:
        Process.kill(config, pid)

    # Give systemd a chance to remove the scope
    time.sleep(0.5)

    Cgroup.clear_default_systemd_scope()

    try:
        cg = Cgroup(SLICE, Version.CGROUP_V2)
        cg.delete()
    except RuntimeError:
        pass


def main(config):
    [result, cause] = prereqs(config)
    if result != consts.TEST_PASSED:
        return [result, cause]

    setup(config)

    try:
        pid = None
        [result, cause, pid] = test(config)
    finally:
        teardown(config, pid)

    return [result, cause]


if __name__ == '__main__':
    config = ftests.parse_args()
    # this test was invoked directly.  run only it
    config.args.num = int(os.path.basename(__file__).split('-')[0])
    sys.exit(ftests.main(config))

# vim: set et ts=4 sw=4:
