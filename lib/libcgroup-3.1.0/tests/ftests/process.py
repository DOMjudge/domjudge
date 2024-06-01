# SPDX-License-Identifier: LGPL-2.1-only
#
# Cgroup class for the libcgroup functional tests
#
# Copyright (c) 2020-2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from container import ContainerError
from cgroup import CgroupVersion
import multiprocessing as mp
from cgroup import Cgroup
from run import RunError
import threading as tp
from run import Run
import time


class Process(object):
    def __init__(self):
        self.children = list()
        self.children_pids = list()

    def __str__(self):
        out_str = 'Process Class\n'
        out_str += '\tchildren = {}\n'.format(self.children)
        out_str += '\tchildren_pids = {}\n'.format(self.children_pids)

        return out_str

    @staticmethod
    def __thread_infinite_loop(config, sleep_time=1):
        while 1:
            time.sleep(sleep_time)

    @staticmethod
    def __infinite_loop(config, sleep_time=1):
        cmd = ["/usr/bin/perl",
               "-e",
               "'while(1){{sleep({})}};'".format(sleep_time)
               ]

        try:
            if config.args.container:
                config.container.run(cmd, shell_bool=True)
            else:
                Run.run(cmd, shell_bool=True)
        except RunError:
            # when the process is killed, a RunError will be thrown.  let's
            # catch and suppress it
            pass

    @staticmethod
    def __cgexec_infinite_loop(config, controller, cgname, sleep_time=1,
                               ignore_systemd=False, replace_idle=False):
        cmd = ["/usr/bin/perl",
               "-e",
               "'while(1){{sleep({})}};'".format(sleep_time)
               ]

        try:
            Cgroup.cgexec(config, controller, cgname, cmd, ignore_systemd=ignore_systemd,
                          replace_idle=replace_idle)
        except RunError:
            # When this process is killed, it will throw a run error.
            # Ignore it.
            pass

    def __save_child_pid(self, config, sleep_time):
        # get the PID of the newly spawned infinite loop
        cmd = (
                "ps x | grep perl | grep 'sleep({})' | awk '{{print $1}}'"
                "".format(sleep_time)
              )

        if config.args.container:
            pid = config.container.run(cmd, shell_bool=True)
        else:
            pid = Run.run(cmd, shell_bool=True)

            for _pid in pid.splitlines():
                self.children_pids.append(int(_pid))

            if pid.find('\n') >= 0:
                # The second pid in the list contains the actual perl process
                pid = pid.splitlines()[1]

        if pid == '' or int(pid) <= 0:
            raise ValueError(
                                'Failed to get the pid of the child process:'
                                '{}'
                                ''.format(pid)
                            )

        return int(pid)

    def create_process(self, config):
        # To allow for multiple processes to be created, each new process
        # sleeps for a different amount of time.  This lets us uniquely find
        # each process later in this function
        sleep_time = len(self.children) + 1

        p = mp.Process(target=Process.__infinite_loop,
                       args=(config, sleep_time, ))
        p.start()

        # wait for the process to start.  If we don't wait, then the getpid
        # logic below may not find the process
        time.sleep(2)

        pid = self.__save_child_pid(config, sleep_time)
        self.children.append(p)

        return pid

    # Create a simple process in the requested cgroup
    def create_process_in_cgroup(self, config, controller, cgname,
                                 cgclassify=True, ignore_systemd=False, replace_idle=False):
        if cgclassify:
            child_pid = self.create_process(config)
            Cgroup.classify(config, controller, cgname, child_pid,
                            ignore_systemd=ignore_systemd, replace_idle=replace_idle)
        else:
            # use cgexec

            # To allow for multiple processes to be created, each new process
            # sleeps for a different amount of time.  This lets us uniquely
            # find each process later in this function
            sleep_time = len(self.children) + 1

            p = mp.Process(target=Process.__cgexec_infinite_loop,
                           args=(config, controller, cgname, sleep_time, ignore_systemd,
                                 replace_idle, ))
            p.start()

            self.children.append(p)

    def create_threaded_process(self, config, threads_cnt):
        threads = list()

        for n in range(threads_cnt):
            sleep_time = n + 1
            thread = tp.Thread(target=Process.__thread_infinite_loop,
                               args=(config, sleep_time, ))
            threads.append(thread)

        for thread in threads:
            thread.start()

    def create_threaded_process_in_cgroup(self, config, controller, cgname,
                                          threads=2, cgclassify=True, ignore_systemd=False,
                                          replace_idle=False):

        p = mp.Process(target=self.create_threaded_process,
                       args=(config, threads, ))
        p.start()

        if cgclassify:
            Cgroup.classify(config, controller, cgname, p.pid,
                            ignore_systemd=ignore_systemd, replace_idle=replace_idle)

        self.children.append(p)
        self.children_pids.append(p.pid)

        return p.pid

    # The caller will block until all children are stopped.
    def join_children(self, config):
        for child in self.children:
            child.join(1)

        for child in self.children_pids:
            try:
                if config.args.container:
                    config.container.run(['kill', str(child)])
                else:
                    Run.run(['kill', str(child)])
            except (RunError, ContainerError):
                # ignore any errors during the kill command.  this is belt
                # and suspenders code
                pass

    @staticmethod
    def __get_cgroup_v1(config, pid, controller):
        cmd = list()

        cmd.append('cat')
        cmd.append('/proc/{}/cgroup'.format(pid))

        if config.args.container:
            ret = config.container.run(cmd)
        else:
            ret = Run.run(cmd)

        for line in ret.splitlines():
            # cgroup v1 appears in /proc/{pid}/cgroup like the following:
            # $ cat /proc/1/cgroup
            # 12:memory:/
            # 11:hugetlb:/
            # 10:perf_event:/
            # 9:rdma:/
            # 8:devices:/
            # 7:cpuset:/
            # 6:blkio:/
            # 5:cpu,cpuacct:/
            # 4:pids:/
            # 3:freezer:/
            # 2:net_cls,net_prio:/
            # 1:name=systemd:/init.scope
            # 0::/init.scope
            proc_controllers = line.split(':')[1]
            if proc_controllers.find(',') >= 0:
                for proc_controller in proc_controllers.split(','):
                    if controller == proc_controller:
                        return line.split(':')[2]
            else:
                if controller == proc_controllers:
                    return line.split(':')[2]

        raise ValueError('Could not get cgroup for pid {} and controller {}'.
                         format(pid, controller))

    @staticmethod
    def __get_cgroup_v2(config, pid, controller):
        cmd = list()

        cmd.append('cat')
        cmd.append('/proc/{}/cgroup'.format(pid))

        if config.args.container:
            ret = config.container.run(cmd)
        else:
            ret = Run.run(cmd)

        for line in ret.splitlines():
            # cgroup v2 appears in /proc/{pid}/cgroup like the following:
            # $ cat /proc/1/cgroup
            # 0::/init.scope
            if line.find('::') < 0:
                # we have identified this controller is cgroup v2,
                # ignore any cgroup v1 controllers
                continue

            return line.split(':')[2]

        raise ValueError(
                            'Could not get cgroup for pid {} and controller {}'
                            ''.format(pid, controller)
                        )

    # given a PID and a cgroup controller, what cgroup is this PID a member of
    @staticmethod
    def get_cgroup(config, pid, controller):
        version = CgroupVersion.get_version(controller)

        if version == CgroupVersion.CGROUP_V1:
            return Process.__get_cgroup_v1(config, pid, controller)
        elif version == CgroupVersion.CGROUP_V2:
            return Process.__get_cgroup_v2(config, pid, controller)

        raise ValueError("get_cgroup() shouldn't reach this point")

    @staticmethod
    def kill(config, pids):
        if not pids:
            return

        if type(pids) == str:
            pids = [int(pid) for pid in pids.splitlines()]
        elif type(pids) == int:
            pids = [pids]

        for pid in pids:
            if config.args.container:
                config.container.run(['kill', '-9', str(pid)])
            else:
                Run.run(['kill', '-9', str(pid)])

# vim: set et ts=4 sw=4:
