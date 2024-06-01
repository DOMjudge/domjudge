/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cg_build_path()
 *
 * Copyright (c) 2019 Oracle and/or its affiliates.  All rights reserved.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

class BuildPathV1Test : public ::testing::Test {
	protected:

	/**
	 * Setup this test case
	 *
	 * This test case calls cg_build_path() to generate various
	 * cgroup paths.  The SetUp() routine creates a simple mount
	 * table that can be used to verify cg_build_path() behavior.
	 *
	 * cg_mount_table for this test is as follows:
	 *	name		mount_point			index
	 *	-----------------------------------------------------
	 *	controller0	/sys/fs/cgroup/controller0	0
	 *	controller1	/sys/fs/cgroup/controller1	1
	 *	controller2	/sys/fs/cgroup/controller2	2
	 *	controller3	/sys/fs/cgroup/controller3	3
	 *	controller4	/sys/fs/cgroup/controller4	4
	 *	controller5	/sys/fs/cgroup/controller5	5
	 *
	 * Note that controllers 1 and 5 are also given namespaces
	 */
	void SetUp() override {
		char NAMESPACE1[] = "ns1";
		char NAMESPACE5[] = "ns5";
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
		}

		// Give a couple of the entries a namespace as well
		cg_namespace_table[1] =	NAMESPACE1;
		cg_namespace_table[5] =	NAMESPACE5;
	}
};

/**
 * No matching controller test
 * @param BuildPathV1Test googletest test case name
 * @param BuildPathV1_ControllerMismatch test name
 *
 * This test will walk through the entire controller mount table
 * and fail to find a match.
 * https://github.com/libcgroup/libcgroup/blob/62f76650db84c0a25f76ece3a79d9d16a1e9f931/src/api.c#L1300
 */
TEST_F(BuildPathV1Test, BuildPathV1_ControllerMismatch)
{
	char *name = NULL;
	char path[FILENAME_MAX];
	/* type intentionally _does not_ match any controllers */
	char type[] = "FOO";
	char *out;

	out = cg_build_path(name, path, type);
	ASSERT_STREQ(out, NULL);
}

/**
 * Matching controller test
 * @param BuildPathV1Test googletest test case name
 * @param BuildPathV1_ControllerMatch test name
 *
 * This test finds a matching controller in the mount table.  Both the
 * namespace and the cgroup name are NULL.
 */
TEST_F(BuildPathV1Test, BuildPathV1_ControllerMatch)
{
	char *name = NULL;
	char path[FILENAME_MAX];
	char type[] = "controller0";
	char *out;

	out = cg_build_path(name, path, type);
	ASSERT_STREQ(out, "/sys/fs/cgroup/controller0/");
}

/**
 * Matching controller test with a cgroup name
 * @param BuildPathV1Test googletest test case name
 * @param BuildPathV1_ControllerMatchWithName test name
 *
 * This test finds a matching controller in the mount table.  The
 * namespace is NULL, but a valid cgroup name is provided.  This
 * exercises the `if (name)` statement
 * https://github.com/libcgroup/libcgroup/blob/62f76650db84c0a25f76ece3a79d9d16a1e9f931/src/api.c#L1289
 */
TEST_F(BuildPathV1Test, BuildPathV1_ControllerMatchWithName)
{
	char name[] = "TomsCgroup1";
	char path[FILENAME_MAX];
	char type[] = "controller3";
	char *out;

	out = cg_build_path(name, path, type);
	ASSERT_STREQ(out, "/sys/fs/cgroup/controller3/TomsCgroup1/");
}

/**
 * Matching controller test with a namespace
 * @param BuildPathV1Test googletest test case name
 * @param BuildPathV1_ControllerMatchWithNs test name
 *
 * This test finds a matching controller in the mount table.  The
 * namespace is valid, but the cgroup name is NULL.  This exercises
 * exercises the `if (cg_namespace_table[i])` statement
 * https://github.com/libcgroup/libcgroup/blob/62f76650db84c0a25f76ece3a79d9d16a1e9f931/src/api.c#L1278
 */
TEST_F(BuildPathV1Test, BuildPathV1_ControllerMatchWithNs)
{
	char *name = NULL;
	char path[FILENAME_MAX];
	char type[] = "controller1";
	char *out;

	out = cg_build_path(name, path, type);
	ASSERT_STREQ(out, "/sys/fs/cgroup/controller1/ns1/");
}

/**
 * Matching controller test with a namespace and a cgroup name
 * @param BuildPathV1Test googletest test case name
 * @param BuildPathV1_ControllerMatchWithNameAndNs test name
 *
 * This test finds a matching controller in the mount table.  Both the
 * namespace and the cgroup name are valid.  This exercises both if
 * statements in cg_build_path_locked().
 */
TEST_F(BuildPathV1Test, BuildPathV1_ControllerMatchWithNameAndNs)
{
	char name[] = "TomsCgroup2";
	char path[FILENAME_MAX];
	char type[] = "controller5";
	char *out;

	out = cg_build_path(name, path, type);
	ASSERT_STREQ(out, "/sys/fs/cgroup/controller5/ns5/TomsCgroup2/");
}
