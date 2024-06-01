# SPDX-License-Identifier: LGPL-2.1-only
#
# Log class for the libcgroup functional tests
#
# Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

import datetime
import consts

log_level = consts.DEFAULT_LOG_LEVEL
log_file = consts.DEFAULT_LOG_FILE
log_fd = None


class Log(object):

    @staticmethod
    def log(msg, msg_level=consts.DEFAULT_LOG_LEVEL):
        global log_level, log_file, log_fd

        if log_level >= msg_level:
            if log_fd is None:
                Log.open_logfd(log_file)

            timestamp = datetime.datetime.now().strftime('%b %d %H:%M:%S')
            log_fd.write('{}: {}\n'.format(timestamp, msg))

    @staticmethod
    def open_logfd(log_file):
        global log_fd

        log_fd = open(log_file, 'a')

    @staticmethod
    def log_critical(msg):
        Log.log('CRITICAL: {}'.format(msg), consts.LOG_CRITICAL)

    @staticmethod
    def log_warning(msg):
        Log.log('WARNING: {}'.format(msg), consts.LOG_WARNING)

    @staticmethod
    def log_debug(msg):
        Log.log('DEBUG: {}'.format(msg), consts.LOG_DEBUG)

# vim: set et ts=4 sw=4:
