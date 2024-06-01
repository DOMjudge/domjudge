# SPDX-License-Identifier: LGPL-2.1-only
#
# Cgroup class for the libcgroup functional tests
#
# Copyright (c) 2020 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from log import Log


class Controller(object):
    # The controller class closely mirrors libcgroup's struct cgroup_controller
    def __init__(self, name):
        self.name = name
        # self.settings maps to
        # struct control_value *values[CG_NV_MAX];
        self.settings = dict()

    def __str__(self):
        out_str = 'Controller {}\n'.format(self.name)

        for setting_key in self.settings:
            out_str += '    {} = {}\n'.format(setting_key,
                                              self.settings[setting_key])

        return out_str

    def __eq__(self, other):
        if not isinstance(other, Controller):
            return False

        if not self.name == other.name:
            return False

        if not self.settings == other.settings:
            self_keys = set(self.settings.keys())
            other_keys = set(other.settings.keys())

            added = other_keys - self_keys
            if added is not None:
                for key in added:
                    Log.log_critical(
                                        'Other contains {} = {}'
                                        ''.format(key, other.settings[key])
                                    )

            removed = self_keys - other_keys
            if removed is not None:
                for key in removed:
                    Log.log_critical(
                                        'Self contains {} = {}'
                                        ''.format(key, self.settings[key])
                                    )

            common = self_keys.intersection(other_keys)
            for key in common:
                if self.settings[key] != other.settings[key]:
                    Log.log_critical(
                                        'self{} = {} while other{} = {}'
                                        ''.format(key, self.settings[key],
                                                  key, other.settings[key])
                                    )

            return False

        return True

# vim: set et ts=4 sw=4:
