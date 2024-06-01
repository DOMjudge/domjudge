# SPDX-License-Identifier: LGPL-2.1-only
#
# Constants for the libcgroup functional tests
#
# Copyright (c) 2019-2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

import os

DEFAULT_LOG_FILE = 'libcgroup-ftests.log'

LOG_CRITICAL = 1
LOG_WARNING = 5
LOG_DEBUG = 8
DEFAULT_LOG_LEVEL = 5

ftest_dir = os.path.dirname(os.path.abspath(__file__))
tests_dir = os.path.dirname(ftest_dir)
LIBCG_MOUNT_POINT = os.path.dirname(tests_dir)

DEFAULT_CONTAINER_NAME = 'TestLibcg'
DEFAULT_CONTAINER_DISTRO = 'ubuntu'
DEFAULT_CONTAINER_RELEASE = '22.04'
DEFAULT_CONTAINER_ARCH = 'amd64'
DEFAULT_CONTAINER_STOP_TIMEOUT = 5

TESTS_RUN_ALL = -1
TESTS_RUN_ALL_SUITES = 'allsuites'
TEST_PASSED = 'passed'
TEST_FAILED = 'failed'
TEST_SKIPPED = 'skipped'

CGRULES_FILE = '/etc/cgrules.conf'

# vim: set et ts=4 sw=4:
