# SPDX-License-Identifier: LGPL-2.1-only
#
# libcgroup python samples Makefile.am
#
# Copyright (c) 2023 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

if WITH_SAMPLES

EXTRA_DIST = create_systemd_scope.py

endif

clean-local: clean-local-check
.PHONY: clean-local-check
clean-local-check:
	-rm -f *.pyc
