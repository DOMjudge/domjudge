# SPDX-License-Identifier: LGPL-2.1-only
#
# Run class for the libcgroup functional tests
#
# Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from subprocess import TimeoutExpired
from log import Log
import subprocess
import time


class Run(object):
    @staticmethod
    def __run(command, shell_bool, timeout):
        if shell_bool:
            if isinstance(command, str):
                # nothing to do.  command is already formatted as a string
                pass
            elif isinstance(command, list):
                command = ' '.join(command)
            else:
                raise ValueError('Unsupported command type')

        subproc = subprocess.Popen(command, shell=shell_bool,
                                   stdout=subprocess.PIPE,
                                   stderr=subprocess.PIPE)

        if timeout:
            try:
                out, err = subproc.communicate(timeout=timeout)
                ret = subproc.returncode

                out = out.strip().decode('UTF-8')
                err = err.strip().decode('UTF-8')
            except TimeoutExpired as te:
                if te.stdout:
                    out = te.stdout.strip().decode('UTF-8')
                else:
                    out = ''
                if te.stderr:
                    err = te.stderr.strip().decode('UTF-8')
                else:
                    err = ''

                if len(err):
                    ret = -1
                else:
                    ret = 0
        else:
            out, err = subproc.communicate()
            ret = subproc.returncode

            out = out.strip().decode('UTF-8')
            err = err.strip().decode('UTF-8')

        if shell_bool:
            Log.log_debug(
                            'run:\n\tcommand = {}\n\tret = {}\n\tstdout = {}'
                            '\n\tstderr = {}'
                            ''.format(command, ret, out, err)
                         )
        else:
            Log.log_debug(
                            'run:\n\tcommand = {}\n\tret = {}\n\tstdout = {}'
                            '\n\tstderr = {}'
                            ''.format(' '.join(command), ret, out, err)
                         )

        return ret, out, err

    @staticmethod
    def run(command, shell_bool=False, ignore_profiling_errors=True, timeout=None):
        ret, out, err = Run.__run(command, shell_bool, timeout)

        if err.find('Error: websocket: bad handshake') >= 0:
            # LXD sometimes throws this error on underpowered machines.
            # Wait a bit, then try the request again
            Log.log_error('Received \'{}\' error.  Re-running command'.format(err))
            time.sleep(5)
            ret, out, err = Run.__run(command, shell_bool, timeout)

        if ret != 0:
            raise RunError("Command '{}' failed".format(''.join(command)),
                           command, ret, out, err)
        if ret != 0 or len(err) > 0:
            if err.find('WARNING: cgroup v2 is not fully supported yet') >= 0:
                # LXD throws the above warning on systems that are fully
                # running cgroup v2.  Ignore this warning, but fail if any
                # other warnings/errors are raised
                pass
            elif ignore_profiling_errors and err.find('profiling') >= 0:
                pass
            else:
                raise RunError("Command '{}' failed".format(''.join(command)),
                               command, ret, out, err)

        return out


class RunError(Exception):
    def __init__(self, message, command, ret, stdout, stderr):
        super(RunError, self).__init__(message)

        self.command = command
        self.ret = ret
        self.stdout = stdout
        self.stderr = stderr

    def __str__(self):
        out_str = 'RunError:\n\tcommand = {}\n\tret = {}'.format(
                  self.command, self.ret)
        out_str += '\n\tstdout = {}\n\tstderr = {}'.format(self.stdout,
                                                           self.stderr)
        return out_str

# vim: set et ts=4 sw=4:
