# SPDX-License-Identifier: LGPL-2.1-only
#
# libcgroup googletests Makefile.am
#
# Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

AM_CPPFLAGS = -I$(top_srcdir)/include \
	      -I$(top_srcdir)/src \
	      -I$(top_srcdir)/src/tools \
	      -I$(top_srcdir)/googletest/googletest/include \
	      -I$(top_srcdir)/googletest/googletest \
	      -std=c++11 \
	      -Wno-write-strings \
	      -DSTATIC= \
	      -DUNIT_TEST
LDADD = $(top_builddir)/src/.libs/libcgroupfortesting.la \
	$(top_builddir)/src/tools/.libs/libcgset.la

EXTRA_DIST = $(top_srcdir)/googletest/googletest/libgtest.so \
	     $(top_srcdir)/googletest/googletest/libgtest_main.so \
	     $(top_srcdir)/googletest/googletest/include \
	     libcgroup_unittest.map

check_PROGRAMS = gtest
TESTS = gtest

gtest_SOURCES = gtest.cpp \
		001-path.cpp \
		002-cgroup_parse_rules_options.cpp \
		003-cg_get_cgroups_from_proc_cgroups.cpp \
		004-cgroup_compare_ignore_rule.cpp \
		005-cgroup_compare_wildcard_procname.cpp \
		006-cgroup_get_cgroup.cpp \
		007-cgroup_process_v1_mount.cpp \
		008-cgroup_process_v2_mount.cpp \
		009-cgroup_set_values_recursive.cpp \
		010-cgroup_chown_chmod_tasks.cpp \
		011-cgroupv2_subtree_control.cpp \
		012-cgroup_create_cgroup.cpp \
		013-cgroup_build_tasks_procs_path.cpp \
		014-cgroupv2_get_subtree_control.cpp \
		015-cgroupv2_controller_enabled.cpp \
		016-cgset_parse_r_flag.cpp \
		017-API_fuzz_test.cpp

gtest_LDFLAGS = -L$(top_srcdir)/googletest/googletest -l:libgtest.so \
		-rpath $(abs_top_srcdir)/googletest/googletest

clean-local:
	${RM} test-procpidcgroup
