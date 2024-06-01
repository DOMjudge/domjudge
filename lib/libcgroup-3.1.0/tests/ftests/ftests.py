#!/usr/bin/env python3
# SPDX-License-Identifier: LGPL-2.1-only
#
# Main entry point for the libcgroup functional tests
#
# Copyright (c) 2019-2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from config import Config
from run import Run
import datetime
import argparse
import consts
import time
import log
import sys
import os

setup_time = 0.0
teardown_time = 0.0

Log = log.Log


def parse_args():
    parser = argparse.ArgumentParser("Libcgroup Functional Tests")
    parser.add_argument(
                            '-n', '--name',
                            help='name of the container',
                            required=False,
                            type=str,
                            default=consts.DEFAULT_CONTAINER_NAME
                        )
    parser.add_argument(
                            '-d', '--distro',
                            help='linux distribution to use as a template',
                            required=False,
                            type=str,
                            default=None
                        )
    parser.add_argument(
                            '-r', '--release',
                            help="distribution release, e.g.'trusty'",
                            required=False,
                            type=str,
                            default=None
                        )
    parser.add_argument(
                            '-a', '--arch',
                            help='processor architecture',
                            required=False,
                            type=str,
                            default=None
                        )
    parser.add_argument(
                            '-t', '--timeout',
                            help='wait timeout (sec) before stopping the '
                                 'container',
                            required=False,
                            type=int,
                            default=None
                        )

    parser.add_argument(
                            '-l', '--loglevel',
                            help='log level',
                            required=False,
                            type=int,
                            default=None
                        )
    parser.add_argument(
                            '-L', '--logfile',
                            help='log file',
                            required=False,
                            type=str,
                            default=None
                        )

    parser.add_argument(
                            '-N', '--num',
                            help='Test number to run.  If unspecified, all '
                                 'tests are run',
                            required=False,
                            default=consts.TESTS_RUN_ALL,
                            type=int
                        )
    parser.add_argument(
                            '-S', '--skip',
                            help="Test number(s) to skip.  If unspecified, all"
                                 " tests are run. To skip multiple tests, "
                                 "separate them via a ',', e.g. '5,7,12'",
                            required=False,
                            default='',
                            type=str
                        )
    parser.add_argument(
                            '-s', '--suite',
                            help='Test suite to run, e.g. cpuset',
                            required=False,
                            default=consts.TESTS_RUN_ALL_SUITES,
                            type=str
                        )

    container_parser = parser.add_mutually_exclusive_group(required=False)
    container_parser.add_argument(
                                    '--container',
                                    action='store_true',
                                    help='Run the tests in a container. Note '
                                         'that some tests cannot be run in a '
                                         'container.',
                                    dest='container'
                                 )
    container_parser.add_argument(
                                    '--no-container',
                                    action='store_false',
                                    help='Do not run the tests in a container.'
                                         ' Note that some tests are '
                                         'destructive and will modify your '
                                         'cgroup hierarchy.',
                                    dest='container'
                                 )
    parser.set_defaults(container=True)

    parser.add_argument(
                            '-v', '--verbose',
                            help='Print all information about this test run',
                            default=True,
                            required=False,
                            action="store_false"
                        )

    config = Config(parser.parse_args())

    if config.args.skip is None or config.args.skip == '':
        pass
    elif config.args.skip.find(',') < 0:
        config.skip_list.append(int(config.args.skip))
    else:
        # multiple tests are being skipped
        for test_num in config.args.skip.split(','):
            config.skip_list.append(int(test_num))

    if config.args.loglevel:
        log.log_level = config.args.loglevel
    if config.args.logfile:
        log.log_file = config.args.logfile

    return config


# this function maps the container UID to the host UID.  By doing
# this, we can write to a bind-mounted device - and thus generate
# code coverage data in the LXD container
def update_host_subuid():
    subuid_line1 = 'lxd:{}:1'.format(os.getuid())
    subuid_line2 = 'root:{}:1'.format(os.getuid())
    found_line1 = False
    found_line2 = False

    with open('/etc/subuid') as ufile:
        for line in ufile.readlines():
            if line.strip() == subuid_line1:
                found_line1 = True
            elif line.strip() == subuid_line2:
                found_line2 = True

    if not found_line1:
        Run.run('sudo sh -c "echo {} >> /etc/subuid"'.format(
                subuid_line1), shell_bool=True)
    if not found_line2:
        Run.run('sudo sh -c "echo {} >> /etc/subuid"'.format(
                subuid_line2), shell_bool=True)


# this function maps the container GID to the host GID.  By doing
# this, we can write to a bind-mounted device - and thus generate
# code coverage data in the LXD container
def update_host_subgid():
    subgid_line1 = 'lxd:{}:1'.format(os.getgid())
    subgid_line2 = 'root:{}:1'.format(os.getgid())
    found_line1 = False
    found_line2 = False

    with open('/etc/subgid') as ufile:
        for line in ufile.readlines():
            if line.strip() == subgid_line1:
                found_line1 = True
            elif line.strip() == subgid_line2:
                found_line2 = True

    if not found_line1:
        Run.run('sudo sh -c "echo {} >> /etc/subgid"'.format(
                subgid_line1), shell_bool=True)
    if not found_line2:
        Run.run('sudo sh -c "echo {} >> /etc/subgid"'.format(
                subgid_line2), shell_bool=True)


def setup(config, do_teardown=True, record_time=False):
    global setup_time

    start_time = time.time()
    if do_teardown:
        # belt and suspenders here.  In case a previous run wasn't properly
        # cleaned up, let's try and clean it up here
        try:
            teardown(config)
        except Exception as e:
            # log but ignore all exceptions
            Log.log_debug(e)

    if config.args.container:
        # this command initializes the lxd storage, networking, etc.
        Run.run(['sudo', 'lxd', 'init', '--auto'])
        update_host_subuid()
        update_host_subgid()

        config.container.create()
        config.container.config()
        config.container.start()

        # add the libcgroup library to the container's ld
        libcgrp_lib_path = os.path.join(consts.LIBCG_MOUNT_POINT, 'src/.libs')
        echo_cmd = ([
                        'bash',
                        '-c',
                        'echo {} >> /etc/ld.so.conf.d/libcgroup.conf'
                        ''.format(libcgrp_lib_path)
                    ])
        config.container.run(echo_cmd)
        config.container.run('ldconfig')

    if record_time:
        setup_time = time.time() - start_time


def run_tests(config):
    passed_tests = []
    failed_tests = []
    skipped_tests = []
    filename_max = 0

    for root, dirs, filenames in os.walk(config.ftest_dir):
        for filename in filenames:
            if os.path.splitext(filename)[-1] != ".py":
                # ignore non-python files
                continue

            filenum = filename.split('-')[0]

            try:
                filenum_int = int(filenum)
            except ValueError:
                # D'oh.  This file must not be a test.  Skip it
                Log.log_debug(
                                'Skipping {}.  It doesn\'t start with an int'
                                ''.format(filename)
                             )
                continue

            try:
                filesuite = filename.split('-')[1]
            except IndexError:
                Log.log_error(
                                'Skipping {}.  It doesn\'t conform to the '
                                'filename format'
                                ''.format(filename)
                             )
                continue

            if config.args.suite == consts.TESTS_RUN_ALL_SUITES or \
               config.args.suite == filesuite:
                if config.args.num == consts.TESTS_RUN_ALL or \
                   config.args.num == filenum_int:

                    if config.args.suite == consts.TESTS_RUN_ALL_SUITES and \
                       filesuite == 'sudo':
                        # Don't run the 'sudo' tests if all tests have been specified.
                        # The sudo tests must be run as sudo and thus need to be separately
                        # invoked.
                        continue

                    if filenum_int in config.skip_list:
                        continue

                    if len(filename) > filename_max:
                        filename_max = len(filename)

                    test = __import__(os.path.splitext(filename)[0])

                    failure_cause = None
                    start_time = time.time()
                    try:
                        Log.log_debug('Running test {}.'.format(filename))
                        [ret, failure_cause] = test.main(config)
                    except Exception as e:
                        # catch all exceptions.  you never know when there's
                        # a crummy test
                        failure_cause = e
                        Log.log_debug(e)
                        ret = consts.TEST_FAILED
                    finally:
                        run_time = time.time() - start_time
                        if ret == consts.TEST_PASSED:
                            passed_tests.append([filename, run_time])
                        elif ret == consts.TEST_FAILED:
                            failed_tests.append([
                                                 filename,
                                                 run_time,
                                                 failure_cause
                                                 ])
                        elif ret == consts.TEST_SKIPPED:
                            skipped_tests.append([
                                                  filename,
                                                  run_time,
                                                  failure_cause
                                                  ])
                        else:
                            raise ValueError('Unexpected ret: {}'.format(ret))

    passed_cnt = len(passed_tests)
    failed_cnt = len(failed_tests)
    skipped_cnt = len(skipped_tests)

    print("-----------------------------------------------------------------")
    print("Test Results:")
    date_str = datetime.datetime.now().strftime('%b %d %H:%M:%S')
    print(
            '\t{}{}'.format(
                                '{0: <35}'.format("Run Date:"),
                                '{0: >15}'.format(date_str)
                            )
         )

    test_str = "{} test(s)".format(passed_cnt)
    print(
            '\t{}{}'.format(
                                '{0: <35}'.format("Passed:"),
                                '{0: >15}'.format(test_str)
                            )
         )

    test_str = "{} test(s)".format(skipped_cnt)
    print(
            '\t{}{}'.format(
                                '{0: <35}'.format("Skipped:"),
                                '{0: >15}'.format(test_str)
                            )
         )

    test_str = "{} test(s)".format(failed_cnt)
    print(
            '\t{}{}'.format(
                                '{0: <35}'.format("Failed:"),
                                '{0: >15}'.format(test_str)
                            )
         )

    for test in failed_tests:
        print(
                "\t\tTest:\t\t\t\t{} - {}"
                ''.format(test[0], str(test[2]))
             )
    print("-----------------------------------------------------------------")

    global setup_time
    global teardown_time
    if config.args.verbose:
        print("Timing Results:")
        print(
                '\t{}{}'.format(
                                    '{0: <{1}}'.format("Test", filename_max),
                                    '{0: >15}'.format("Time (sec)")
                                )
             )
        print(
                # 15 is padding space of "Time (sec)"
                '\t{}'.format('-' * (filename_max + 15))
             )
        time_str = "{0: 2.2f}".format(setup_time)
        print(
                '\t{}{}'.format(
                                    '{0: <{1}}'.format('setup', filename_max),
                                    '{0: >15}'.format(time_str)
                                )
             )

        all_tests = passed_tests + skipped_tests + failed_tests
        all_tests.sort()
        for test in all_tests:
            time_str = "{0: 2.2f}".format(test[1])
            print(
                    '\t{}{}'.format(
                                        '{0: <{1}}'.format(test[0],
                                                           filename_max),
                                        '{0: >15}'.format(time_str)
                                    )
                  )
        time_str = "{0: 2.2f}".format(teardown_time)
        print(
                '\t{}{}'
                ''.format(
                            '{0: <{1}}'.format('teardown', filename_max),
                            '{0: >15}'.format(time_str)
                          )
             )

        total_run_time = setup_time + teardown_time
        for test in passed_tests:
            total_run_time += test[1]
        for test in failed_tests:
            total_run_time += test[1]
        total_str = "{0: 5.2f}".format(total_run_time)
        print('\t{}'.format('-' * (filename_max + 15)))
        print(
                '\t{}{}'
                ''.format(
                            '{0: <{1}}'
                            ''.format("Total Run Time", filename_max),
                            '{0: >15}'.format(total_str)
                         )
              )

    return [passed_cnt, failed_cnt, skipped_cnt]


def teardown(config, record_time=False):
    global teardown_time
    start_time = time.time()

    config.process.join_children(config)

    if config.args.container:
        try:
            config.container.stop()
        except Exception as e:
            # log but ignore all exceptions
            Log.log_debug(e)
        try:
            config.container.delete()
        except Exception as e:
            # log but ignore all exceptions
            Log.log_debug(e)

    if record_time:
        teardown_time = time.time() - start_time


def main(config):
    AUTOMAKE_SKIPPED = 77
    AUTOMAKE_HARD_ERROR = 99
    AUTOMAKE_PASSED = 0

    try:
        setup(config, record_time=True)
        [passed_cnt, failed_cnt, skipped_cnt] = run_tests(config)
    finally:
        teardown(config, record_time=True)

    if failed_cnt > 0:
        return failed_cnt
    if passed_cnt > 0:
        return AUTOMAKE_PASSED
    if skipped_cnt > 0:
        return AUTOMAKE_SKIPPED

    return AUTOMAKE_HARD_ERROR


if __name__ == '__main__':
    config = parse_args()
    sys.exit(main(config))

# vim: set et ts=4 sw=4:
