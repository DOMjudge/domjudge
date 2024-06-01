/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroup_compare_ignore_rule()
 *
 * Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

class CgroupCompareIgnoreRuleTest : public ::testing::Test {
};

static void CreateCgroupProcFile(const char * const contents)
{
	FILE *f;

	f = fopen(TEST_PROC_PID_CGROUP_FILE, "w");
	ASSERT_NE(f, nullptr);

	fprintf(f, "%s", contents);
	fclose(f);
}

TEST_F(CgroupCompareIgnoreRuleTest, NotAnIgnore)
{
	char procname[] = "myprocess";
	struct cgroup_rule rule;
	pid_t pid = 1234;
	bool ret;

	rule.is_ignore = false;

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, SimpleMatch)
{
	char proc_file_contents[] =
		"7:cpuacct:/SimpleMatchCgroup";
	char rule_controller[] = "cpuacct";
	char procname[] = "procfoo";
	struct cgroup_rule rule;
	pid_t pid = 2345;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = NULL;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "SimpleMatchCgroup");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, CgroupsDontMatch)
{
	char proc_file_contents[] =
		"2:cpuacct:CloseButNotQuite";
	char rule_controller[] = "cpuacct";
	char procname[] = "procfoo2";
	struct cgroup_rule rule;
	pid_t pid = 4567;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "CloseButNotQuit");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, ControllersDontMatch)
{
	char proc_file_contents[] =
		"5:memory:MyCgroup";
	char rule_controller[] = "cpuacct";
	char procname[] = "procfoo3";
	struct cgroup_rule rule;
	pid_t pid = 5678;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "MyCgroup");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, CombinedControllers)
{
	char proc_file_contents[] =
		"13:cpu,cpuacct:/containercg";
	char rule_controller[] = "cpuacct";
	char procname[] = "docker";
	struct cgroup_rule rule = {0};
	pid_t pid = 6789;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	rule.controllers[1] = NULL;
	sprintf(rule.destination, "containercg");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, MatchChildFolder)
{
	char proc_file_contents[] =
		"7:cpuset:/parentcg/childcg/grandchildcg";
	char rule_controller[] = "cpuset";
	char procname[] = "childprocess";
	struct cgroup_rule rule;
	pid_t pid = 7890;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "parentcg/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, MatchGrandchildFolder)
{
	char proc_file_contents[] =
		"1:hugetlb:/parentcg/childcg/grandchildcg";
	char rule_controller[] = "hugetlb";
	char procname[] = "granchildprocess";
	struct cgroup_rule rule;
	pid_t pid = 8901;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = NULL;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "parentcg/childcg/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

/**
 * This test is designed to highlight the case where the user has not put a
 * trailing slash at the end of the rule's destination.  By design, this will
 * cause the rule to match a wide variety of cases.
 *
 * For example, given the rule destination of "Folder".  The following
 * behavior would be observed:
 *	Process Location	Matches the rule?
 *	Folder			Yes
 *	Folders			Yes
 *	Folder/AnotherFolder	Yes
 *	Folder2			Yes
 *	Folder3/ChildFolder	Yes
 *	Folde			No
 */
TEST_F(CgroupCompareIgnoreRuleTest, MatchSimilarChildFolder)
{
	char proc_file_contents[] =
		"1:hugetlb:/parentcg/childcg2";
	char rule_controller[] = "hugetlb";
	char procname[] = "granchildprocess";
	struct cgroup_rule rule;
	pid_t pid = 8901;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = NULL;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "parentcg/childcg");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, RealWorldMatch)
{
	char proc_file_contents[] =
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
	char rule_controller[] = "cpu";
	char procname[] = "granchildprocess";
	struct cgroup_rule rule;
	pid_t pid = 8901;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = NULL;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "myCgroup/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, RealWorldNoMatch)
{
	char proc_file_contents[] =
		"12:memory:/user/johndoe/0\n"
		"11:perf_event:/\n"
		"10:rdma:/\n"
		"9:blkio:/user.slice\n"
		"8:cpu,cpuacct:/myCgroup\n"
		"7:freezer:/user/johndoe/0\n"
		"6:net_cls,net_prio:/NetCgroup\n"
		"5:pids:/user.slice/user-1000.slice/session-1.scope\n"
		"4:devices:/user.slice\n"
		"3:cpuset:/\n"
		"2:hugetlb:/\n"
		"1:name=systemd:/user.slice/user-1000.slice/session-1.scope\n"
		"0::/user.slice/user-1000.slice/session-1.scope\n";
	char rule_controller[] = "net_cls";
	char procname[] = "NotMatching";
	struct cgroup_rule rule;
	pid_t pid = 9012;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = NULL;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "NetCgroup2");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, SimilarFolderNoMatch)
{
	char proc_file_contents[] =
		"4:memory:/folder1";
	char rule_controller[] = "memory";
	char procname[] = "childprocess";
	struct cgroup_rule rule;
	pid_t pid = 2345;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "folder/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, RootDestinationMatch)
{
	char proc_file_contents[] =
		"2:freezer:/";
	char rule_controller[] = "freezer";
	char procname[] = "ANewProcess";
	struct cgroup_rule rule;
	pid_t pid = 3456;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, RootDestinationNoMatch)
{
	char proc_file_contents[] =
		"2:freezer:/somerandomcg";
	char rule_controller[] = "freezer";
	char procname[] = "ANewProcess";
	struct cgroup_rule rule;
	pid_t pid = 3456;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "/");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}

TEST_F(CgroupCompareIgnoreRuleTest, WildcardProcnameSimpleMatch)
{
	char proc_file_contents[] =
		"7:cpuacct:/MatchCgroup";
	char rule_controller[] = "cpuacct";
	char rule_procname[] = "ssh*";
	char procname[] = "sshd";
	struct cgroup_rule rule;
	pid_t pid = 1234;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = rule_procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "MatchCgroup");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, true);
}

TEST_F(CgroupCompareIgnoreRuleTest, WildcardProcnameNoMatch)
{
	char proc_file_contents[] =
		"7:cpuacct:/AnotherCgroup";
	char rule_controller[] = "cpuacct";
	char rule_procname[] = "httpd*";
	char procname[] = "httpx";
	struct cgroup_rule rule;
	pid_t pid = 1234;
	bool ret;

	CreateCgroupProcFile(proc_file_contents);

	rule.procname = rule_procname;
	rule.is_ignore = true;
	rule.controllers[0] = rule_controller;
	sprintf(rule.destination, "AnotherCgroup");

	ret = cgroup_compare_ignore_rule(&rule, pid, procname);
	ASSERT_EQ(ret, false);
}
