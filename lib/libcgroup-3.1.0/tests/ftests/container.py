# SPDX-License-Identifier: LGPL-2.1-only
#
# Container class for the libcgroup functional tests
#
# Copyright (c) 2019-2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from run import Run, RunError
from queue import Queue
import threading as tp
from log import Log
import consts
import time
import os


class Container(object):
    def __init__(self, name, stop_timeout=None, arch=None, cfg_path=None,
                 distro=None, release=None):
        self.name = name
        self.privileged = True

        if stop_timeout:
            self.stop_timeout = stop_timeout
        else:
            self.stop_timeout = consts.DEFAULT_CONTAINER_STOP_TIMEOUT

        if arch:
            self.arch = arch
        else:
            self.arch = consts.DEFAULT_CONTAINER_ARCH

        if distro:
            self.distro = distro
        else:
            self.distro = consts.DEFAULT_CONTAINER_DISTRO

        if release:
            self.release = release
        else:
            self.release = consts.DEFAULT_CONTAINER_RELEASE

        ftest_dir = os.path.dirname(os.path.abspath(__file__))
        tests_dir = os.path.dirname(ftest_dir)
        # save off the path to the libcgroup source code
        self.libcg_dir = os.path.dirname(tests_dir)

    def __str__(self):
        out_str = 'Container {}'.format(self.name)
        out_str += '\n\tdistro = {}'.format(self.distro)
        out_str += '\n\trelease = {}'.format(self.release)
        out_str += '\n\tarch = {}'.format(self.arch)
        out_str += '\n\tstop_timeout = {}\n'.format(self.stop_timeout)

        return out_str

    # configure the container to meet our needs
    def config(self):
        # map our UID and GID to the same UID/GID in the container
        cmd = (
                    'printf "uid {} 1000\ngid {} 1000" | sudo lxc config set '
                    '{} raw.idmap -'
                    ''.format(os.getuid(), os.getgid(), self.name)
              )
        Run.run(cmd, shell_bool=True)

        # add the libcgroup root directory (where we did the build) into
        # the container
        cmd2 = list()
        if self.privileged:
            cmd2.append('sudo')
        cmd2.append('lxc')
        cmd2.append('config')
        cmd2.append('device')
        cmd2.append('add')
        cmd2.append(self.name)
        cmd2.append('libcgsrc')  # arbitrary name of device
        cmd2.append('disk')
        # to appease gcov, mount the libcgroup source at the same path as we
        # built it.  This can be worked around someday by using
        # GCOV_PREFIX_STRIP, but that was more difficult to setup than just
        # doing this initially
        cmd2.append('source={}'.format(self.libcg_dir))
        cmd2.append('path={}'.format(self.libcg_dir))

        return Run.run(cmd2)

    def _init_container(self, q):
        cmd = list()

        if self.privileged:
            cmd.append('sudo')

        cmd.append('lxc')
        cmd.append('init')

        cmd.append('{}:{}'.format(self.distro, self.release))

        cmd.append(self.name)

        try:
            Run.run(cmd)
            q.put(True)
        except Exception:  # noqa: E722
            q.put(False)
        except BaseException:  # noqa: E722
            q.put(False)

    def create(self):
        # Github Actions sometimes has timeout issues with the LXC sockets.
        # Try this command multiple times in an attempt to work around this
        # limitation

        queue = Queue()
        sleep_time = 5
        ret = False

        for i in range(5):
            thread = tp.Thread(target=self._init_container, args=(queue, ))
            thread.start()

            time_cnt = 0
            while thread.is_alive():
                time.sleep(sleep_time)
                time_cnt += sleep_time
                Log.log_debug('Waiting... {}'.format(time_cnt))

            ret = queue.get()
            if ret:
                break
            else:
                try:
                    self.delete()
                except RunError:
                    pass

            thread.join()

        if not ret:
            raise ContainerError('Failed to create the container')

    def delete(self):
        cmd = list()

        if self.privileged:
            cmd.append('sudo')

        cmd.append('lxc')
        cmd.append('delete')

        cmd.append(self.name)

        return Run.run(cmd)

    def run(self, cntnr_cmd, shell_bool=False):
        cmd = list()

        if self.privileged:
            cmd.append('sudo')

        cmd.append('lxc')
        cmd.append('exec')

        cmd.append(self.name)

        cmd.append('--')

        # concatenate the lxc exec command with the command to be run
        # inside the container
        if isinstance(cntnr_cmd, str):
            cmd.append(cntnr_cmd)
        elif isinstance(cntnr_cmd, list):
            cmd = cmd + cntnr_cmd
        else:
            raise ContainerError('Unsupported command type')

        return Run.run(cmd, shell_bool=shell_bool)

    def start(self):
        cmd = list()

        if self.privileged:
            cmd.append('sudo')

        cmd.append('lxc')
        cmd.append('start')

        cmd.append(self.name)

        return Run.run(cmd)

    def stop(self, force=True):
        cmd = list()

        if self.privileged:
            cmd.append('sudo')

        cmd.append('lxc')
        cmd.append('stop')

        cmd.append(self.name)

        if force:
            cmd.append('-f')
        else:
            cmd.append('--timeout')
            cmd.append(str(self.stop_timeout))

        return Run.run(cmd)


class ContainerError(Exception):
    def __init__(self, message):
        super(ContainerError, self).__init__(message)

    def __str__(self):
        out_str = 'ContainerError:\n\tmessage = {}'.format(self.message)
        return out_str

# vim: set et ts=4 sw=4:
