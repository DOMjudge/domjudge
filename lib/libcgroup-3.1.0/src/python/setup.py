#!/usr/bin/env python
# SPDX-License-Identifier: LGPL-2.1-only
#
# Libcgroup Python Module Build Script
#

#
# Copyright (c) 2021-2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

from setuptools import Extension, setup
from Cython.Build import cythonize
import os

setup(
    name='libcgroup',
    version=os.environ['VERSION_RELEASE'],
    description='Python bindings for libcgroup',
    url='https://github.com/libcgroup/libcgroup',
    maintainer='Tom Hromatka',
    maintainer_email='tom.hromatka@oracle.com',
    license='LGPLv2.1',
    platforms='Linux',
    ext_modules=cythonize([
            Extension(
                      'libcgroup', ['libcgroup.pyx'],
                      # unable to handle libtool libraries directly
                      extra_objects=['../.libs/libcgroup.a'],
                      libraries=['systemd']
                     ),
             ])
)

# vim: set et ts=4 sw=4:
