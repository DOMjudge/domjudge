# SPDX-License-Identifier: LGPL-2.1-only
#
# Utility functions for the libcgroup functional tests
#
# Copyright (c) 2020 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from run import Run


# function to indent a block of text by cnt number of spaces
def indent(in_str, cnt):
    leading_indent = cnt * ' '
    return ''.join(leading_indent + line for line in in_str.splitlines(True))


def get_file_owner_uid(config, filename):
    cmd = list()
    cmd.append('stat')
    cmd.append('-c')
    cmd.append("'%u'")
    cmd.append(filename)

    if config.args.container:
        return int(config.container.run(cmd, shell_bool=True))
    else:
        return int(Run.run(cmd, shell_bool=True))


def get_file_owner_username(config, filename):
    cmd = list()
    cmd.append('stat')
    cmd.append('-c')
    cmd.append("'%U'")
    cmd.append(filename)

    if config.args.container:
        return config.container.run(cmd, shell_bool=True)
    else:
        return Run.run(cmd, shell_bool=True)


def get_file_owner_gid(config, filename):
    cmd = list()
    cmd.append('stat')
    cmd.append('-c')
    cmd.append("'%g'")
    cmd.append(filename)

    if config.args.container:
        return int(config.container.run(cmd, shell_bool=True))
    else:
        return int(Run.run(cmd, shell_bool=True))


def get_file_owner_group_name(config, filename):
    cmd = list()
    cmd.append('stat')
    cmd.append('-c')
    cmd.append("'%G'")
    cmd.append(filename)

    if config.args.container:
        return config.container.run(cmd, shell_bool=True)
    else:
        return Run.run(cmd, shell_bool=True)


def get_file_permissions(config, filename):
    cmd = list()
    cmd.append('stat')
    cmd.append('-c')
    cmd.append("'%a'")
    cmd.append(filename)

    if config.args.container:
        return config.container.run(cmd, shell_bool=True)
    else:
        return Run.run(cmd, shell_bool=True)

# vim: set et ts=4 sw=4:
