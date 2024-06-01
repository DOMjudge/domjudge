/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroup_build_tasks_procs_path()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

class BuildTasksProcPathTest : public ::testing::Test {
	protected:

	/**
	 * Setup this test case
	 *
	 * This test case calls cg_build_path() to generate various
	 * cgroup paths.  The SetUp() routine creates a simple mount
	 * table that can be used to verify cg_build_path() behavior.
	 *
	 * cg_mount_table for this test is as follows:
	 *	name		mount_point		   index  version
	 *	----------------------------------------------------------
	 *	controller0	/sys/fs/cgroup/controller0     0      UNK
	 *	controller1	/sys/fs/cgroup/controller1     1        2
	 *	controller2	/sys/fs/cgroup/controller2     2        1
	 *	controller3	/sys/fs/cgroup/controller3     3        2
	 *	controller4	/sys/fs/cgroup/controller4     4        1
	 *	controller5	/sys/fs/cgroup/controller5     5        2
	 *
	 * Note that controllers 1 and 4 are also given namespaces
	 */
	void SetUp() override {
		char NAMESPACE1[] = "ns1";
		char NAMESPACE4[] = "ns4";
		const int ENTRY_CNT = 6;
		int i, ret;

		memset(&cg_mount_table, 0, sizeof(cg_mount_table));
		memset(cg_namespace_table, 0,
			CG_CONTROLLER_MAX * sizeof(cg_namespace_table[0]));

		// Populate the mount table
		for (i = 0; i < ENTRY_CNT; i++) {
			snprintf(cg_mount_table[i].name, CONTROL_NAMELEN_MAX,
				 "controller%d", i);
			cg_mount_table[i].index = i;

			ret = snprintf(cg_mount_table[i].mount.path, FILENAME_MAX,
				 "/sys/fs/cgroup/%s", cg_mount_table[i].name);
			ASSERT_LT(ret, sizeof(cg_mount_table[i].mount.path));

			cg_mount_table[i].mount.next = NULL;

			if (i == 0)
				cg_mount_table[i].version = CGROUP_UNK;
			else
				cg_mount_table[i].version =
					(cg_version_t)((i % 2) + 1);
		}

		// Give a couple of the entries a namespace as well
		cg_namespace_table[1] =	NAMESPACE1;
		cg_namespace_table[4] =	NAMESPACE4;
	}
};

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_ControllerNotFound)
{
	char ctrlname[] = "InvalidCtrlr";
	char path[FILENAME_MAX];
	char cgname[] = "foo";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, ECGOTHER);
	ASSERT_STREQ(path, "\0");
}

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_UnknownCgVersion)
{
	char ctrlname[] = "controller0";
	char path[FILENAME_MAX];
	char cgname[] = "bar";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, ECGOTHER);
	ASSERT_STREQ(path, "\0");
}

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_CgV1)
{
	char ctrlname[] = "controller2";
	char path[FILENAME_MAX];
	char cgname[] = "Container7";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(path, "/sys/fs/cgroup/controller2/Container7/tasks");
}

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_CgV2)
{
	char ctrlname[] = "controller3";
	struct cgroup_controller ctrlr = {0};
	char path[FILENAME_MAX];
	char cgname[] = "tomcat";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(path, "/sys/fs/cgroup/controller3/tomcat/cgroup.procs");
}

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_CgV1WithNs)
{
	char ctrlname[] = "controller4";
	struct cgroup_controller ctrlr = {0};
	char path[FILENAME_MAX];
	char cgname[] = "database12";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(path, "/sys/fs/cgroup/controller4/ns4/database12/tasks");
}

TEST_F(BuildTasksProcPathTest, BuildTasksProcPathTest_CgV2WithNs)
{
	char ctrlname[] = "controller1";
	struct cgroup_controller ctrlr = {0};
	char path[FILENAME_MAX];
	char cgname[] = "server";
	int ret;

	ret = cgroup_build_tasks_procs_path(path, sizeof(path), cgname,
					    ctrlname);
	ASSERT_EQ(ret, 0);
	ASSERT_STREQ(path, "/sys/fs/cgroup/controller1/ns1/server/cgroup.procs");
}
