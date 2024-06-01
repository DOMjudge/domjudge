/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cg_get_cgroups_from_proc_cgroups()
 *
 * Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

class GetCgroupsFromProcCgroupsTest : public ::testing::Test {
};

static void CreateCgroupProcFile(const char * const contents)
{
	FILE *f;

	f = fopen(TEST_PROC_PID_CGROUP_FILE, "w");
	ASSERT_NE(f, nullptr);

	fprintf(f, "%s", contents);
	fclose(f);
}


TEST_F(GetCgroupsFromProcCgroupsTest, ReadSingleLine)
{
#undef LIST_LEN
#define LIST_LEN 3
	char contents[] =
		"5:pids:/user.slice/user-1000.slice/session-1.scope\n";
	char *controller_list[LIST_LEN];
	char *cgroup_list[LIST_LEN];
	pid_t pid = 1234;
	int ret, i;

	for (i = 0; i < LIST_LEN; i++) {
		controller_list[i] = NULL;
		cgroup_list[i] = NULL;
	}

	CreateCgroupProcFile(contents);

	ret = cg_get_cgroups_from_proc_cgroups(pid, cgroup_list,
			controller_list, LIST_LEN);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(controller_list[0], "pids");
	ASSERT_STREQ(cgroup_list[0],
		     "user.slice/user-1000.slice/session-1.scope");
}

TEST_F(GetCgroupsFromProcCgroupsTest, ReadSingleLine2)
{
#undef LIST_LEN
#define LIST_LEN 1
	char contents[] =
		"5:cpu,cpuacct:/\n";
	char *controller_list[LIST_LEN];
	char *cgroup_list[LIST_LEN];
	pid_t pid = 1234;
	int ret, i;

	for (i = 0; i < LIST_LEN; i++) {
		controller_list[i] = NULL;
		cgroup_list[i] = NULL;
	}

	CreateCgroupProcFile(contents);

	ret = cg_get_cgroups_from_proc_cgroups(pid, cgroup_list,
			controller_list, LIST_LEN);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(controller_list[0], "cpu,cpuacct");
	ASSERT_STREQ(cgroup_list[0], "/");
}

TEST_F(GetCgroupsFromProcCgroupsTest, ReadEmptyController)
{
#undef LIST_LEN
#define LIST_LEN 1
	char contents[] =
		"0::/user.slice/user-1000.slice/session-1.scope\n";
	char *controller_list[LIST_LEN];
	char *cgroup_list[LIST_LEN];
	pid_t pid = 1234;
	int ret, i;

	for (i = 0; i < LIST_LEN; i++) {
		controller_list[i] = NULL;
		cgroup_list[i] = NULL;
	}

	CreateCgroupProcFile(contents);

	ret = cg_get_cgroups_from_proc_cgroups(pid, cgroup_list,
			controller_list, LIST_LEN);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(controller_list[0], nullptr);
	ASSERT_EQ(cgroup_list[0], nullptr);
}

TEST_F(GetCgroupsFromProcCgroupsTest, ReadExampleFile)
{
	char contents[] =
		"12:memory:/user/johndoe/0\n"
		"11:perf_event:/\n"
		"10:rdma:/\n"
		"9:blkio:/user.slice\n"
		"8:cpu,cpuacct:/myCgroup\n"
		"7:freezer:/user/johndoe/0\n"
		"6:net_cls,net_prio:/\n"
		"5:pids:/user.slice/user-1000.slice/session-1.scope\n"
		"4:devices:/user.slice\n"
		"3:cpuset:/\n"
		"2:hugetlb:/\n"
		"1:name=systemd:/user.slice/user-1000.slice/session-1.scope\n"
		"0::/user.slice/user-1000.slice/session-1.scope\n";
	char *controller_list[MAX_MNT_ELEMENTS];
	char *cgroup_list[MAX_MNT_ELEMENTS];
	pid_t pid = 5678;
	int ret, i;

	for (i = 0; i < MAX_MNT_ELEMENTS; i++) {
		controller_list[i] = NULL;
		cgroup_list[i] = NULL;
	}

	CreateCgroupProcFile(contents);

	ret = cg_get_cgroups_from_proc_cgroups(pid, cgroup_list,
			controller_list, MAX_MNT_ELEMENTS);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(controller_list[0], "memory");
	ASSERT_STREQ(cgroup_list[0], "user/johndoe/0");
	ASSERT_STREQ(controller_list[1], "perf_event");
	ASSERT_STREQ(cgroup_list[1], "/");
	ASSERT_STREQ(controller_list[2], "rdma");
	ASSERT_STREQ(cgroup_list[2], "/");
	ASSERT_STREQ(controller_list[3], "blkio");
	ASSERT_STREQ(cgroup_list[3], "user.slice");
	ASSERT_STREQ(controller_list[4], "cpu,cpuacct");
	ASSERT_STREQ(cgroup_list[4], "myCgroup");
	ASSERT_STREQ(controller_list[5], "freezer");
	ASSERT_STREQ(cgroup_list[5], "user/johndoe/0");
	ASSERT_STREQ(controller_list[6], "net_cls,net_prio");
	ASSERT_STREQ(cgroup_list[6], "/");
	ASSERT_STREQ(controller_list[7], "pids");
	ASSERT_STREQ(cgroup_list[7], "user.slice/user-1000.slice/session-1.scope");
	ASSERT_STREQ(controller_list[8], "devices");
	ASSERT_STREQ(cgroup_list[8], "user.slice");
	ASSERT_STREQ(controller_list[9], "cpuset");
	ASSERT_STREQ(cgroup_list[9], "/");
	ASSERT_STREQ(controller_list[10], "hugetlb");
	ASSERT_STREQ(cgroup_list[10], "/");
	ASSERT_STREQ(controller_list[11], "name=systemd");
	ASSERT_STREQ(cgroup_list[11], "user.slice/user-1000.slice/session-1.scope");

	ASSERT_EQ(controller_list[12], nullptr);
	ASSERT_EQ(cgroup_list[12], nullptr);
}
