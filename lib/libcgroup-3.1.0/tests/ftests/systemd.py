# SPDX-License-Identifier: LGPL-2.1-only
#
# Systemd class for the libcgroup functional tests
#
# Copyright (c) 2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from run import Run, RunError
from cgroup import Cgroup
import os


class Systemd(object):
    @staticmethod
    def is_delegated(config, scope_name):
        cmd = ['systemctl', 'show', '-P', 'Delegate', scope_name]
        try:
            out = Run.run(cmd, shell_bool=True)

            if out == 'yes':
                return True
            else:
                return False
        except RunError as re:
            if re.stderr.find('invalid option') >= 0:
                # This version of systemd is too old for the '-P' flag.  At this time, I don't
                # think there's a way to verify the scope is delegated.  Lie and return true
                # until we figure out something better :(
                return True
            raise re

    # This function creates a task and writes its pid into systemd
    # configuration file, that gets passed to the cgconfigparser tool to
    # create systemd slice/scope.
    @staticmethod
    def write_config_with_pid(config, config_fname, _slice, scope, setdefault="yes"):
        pid = config.process.create_process(config)
        config_file = '''systemd {{
            slice = {};
            scope = {};
            setdefault = {};
            pid = {};
        }}'''.format(_slice, scope, setdefault, pid)

        f = open(config_fname, 'w')
        f.write(config_file)
        f.close()

        return pid

    # Stopping the systemd scope, will kill the default task in the scope
    # and remove scope cgroup but will not remove the slice, that needs to
    # removed manually.
    @staticmethod
    def remove_scope_slice_conf(config, _slice, scope, controller, config_fname=None):
        if config_fname:
            os.remove(config_fname)

        try:
            if config.args.container:
                config.container.run(['systemctl', 'stop', '{}'.format(scope)],
                                     shell_bool=True)
            else:
                Run.run(['sudo', 'systemctl', 'stop', '{}'.format(scope)], shell_bool=True)
        except RunError as re:
            if 'scope not loaded' in re.stderr:
                raise re

        # In case the error occurs before the creation of slice/scope and
        # we may very well be on the teardown path, ignore the exception
        try:
            Cgroup.delete(config, controller, cgname=_slice, ignore_systemd=True)
        except RunError as re:
            if 'No such file or directory' not in re.stderr:
                raise re
