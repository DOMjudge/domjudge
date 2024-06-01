/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for fuzz testing APIs with negative values.
 *
 * Copyright (c) 2023 Oracle and/or its affiliates.  All rights reserved.
 * Author: Kamalesh Babulal <kamalesh.babulal@oracle.com>
 */

#include <sys/stat.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

class APIArgsTest: public :: testing:: Test {
	protected:

	void SetUp() override {
		/* Stub */
	}
};

/**
 * Pass NULL cgroup for setting permissions
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_permissions test name
 *
 * This test will pass NULL cgroup to the cgroup_set_permissions()
 * and check it handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_set_permissions)
{
	mode_t dir_mode, ctrl_mode, task_mode;
	struct cgroup * cgroup = NULL;

	dir_mode = (S_IRWXU | S_IXGRP | S_IXOTH);
	ctrl_mode = (S_IRUSR | S_IWUSR | S_IRGRP);
	task_mode = (S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP);

	testing::internal::CaptureStdout();

	cgroup_set_permissions(cgroup, dir_mode, ctrl_mode, task_mode);

	std::string result = testing::internal::GetCapturedStdout();
	ASSERT_EQ(result, "Error: Cgroup, operation not allowed\n");
}

/**
 * Pass NULL cgroup name for creating a cgroup
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_new_cgroup test name
 *
 * This test will pass NULL cgroup name to the cgroup_new_cgroup()
 * and check it handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_new_cgroup)
{
	struct cgroup *cgroup = NULL;
	char *name = NULL;

	cgroup = cgroup_new_cgroup(name);
	ASSERT_EQ(cgroup, nullptr);
}

/**
 * Test arguments passed to set a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_string test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_set_value_string() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_set_value_string)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	char * name = NULL, *value = NULL;
	struct cgroup *cgroup = NULL;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_set_value_string(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	ret = cgroup_set_value_string(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	name = strdup("cgroup.shares");
	ASSERT_NE(name, nullptr);

	// case 3
	// cgc = valid, name = valid, value = NULL
	ret = cgroup_set_value_string(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	free(name);
	name = NULL;

	value = strdup("1024");
	ASSERT_NE(value, nullptr);

	// case 4
	// cgc = valid, name = NULL, value = valid
	ret = cgroup_set_value_string(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	free(value);
}

/**
 * Test arguments passed to get a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_get_value_string test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_get_value_string() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_get_value_string)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	char *name = NULL, *value = NULL;
	struct cgroup *cgroup = NULL;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_get_value_string(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	ret = cgroup_get_value_string(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	name = strdup("cgroup.shares");
	ASSERT_NE(name, nullptr);

	// case 3
	// cgc = valid, name = valid, value = NULL
	ret = cgroup_get_value_string(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	free(name);
	name = NULL;

	// case 4
	// cgc = valid, name = valid, value = NULL
	ret = cgroup_get_value_string(cgc, name, &value);
	ASSERT_EQ(ret, 50011);

	free(value);
}

/**
 * Test arguments passed to add controller to a cgroup
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_string test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_add_controller() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_add_controller)
{
	const char * const cg_name = "FuzzerCgroup";
	const char * const cg_ctrl = "cpu";
	const char * const new_cg_ctrl = NULL;
	struct cgroup_controller *cgc = NULL;
	struct cgroup *cgroup = NULL;
	int ret;

	// case 1
	// cgrp = NULL, name = NULL
	cgc = cgroup_add_controller(cgroup, new_cg_ctrl);
	ASSERT_EQ(cgroup, nullptr);

	// case 2
	// cgrp = NULL, name = valid
	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_EQ(cgroup, nullptr);

	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// case 3
	// cgrp = valid, name = NULL
	cgc = cgroup_add_controller(cgroup, new_cg_ctrl);
	ASSERT_EQ(cgc, nullptr);
}

/**
 * Test arguments passed to add a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_string test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_add_value_string() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_add_value_string)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller *cgc = NULL;
	const char * const cg_ctrl = "cpu";
	struct cgroup *cgroup = NULL;
	char * ctrl_value = NULL;
	char * ctrl_name = NULL;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_add_value_string(cgc, ctrl_name, ctrl_value);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	ret = cgroup_add_value_string(cgc, ctrl_name, ctrl_value);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = NULL
	ctrl_name = strdup("cpu.shares");
	ASSERT_NE(ctrl_name, nullptr);

	ret = cgroup_add_value_string(cgc, ctrl_name, ctrl_value);
	ASSERT_EQ(ret, 0);

	// case 4
	// cgc = valid, name = NULL, value = valid
	free(ctrl_name);
	ctrl_name = NULL;

	ctrl_value = strdup("1024");
	ASSERT_NE(ctrl_value, nullptr);

	ret = cgroup_add_value_string(cgc, ctrl_name, ctrl_value);
	ASSERT_EQ(ret, 50011);

	free(ctrl_value);
}

TEST_F(APIArgsTest, API_cgroup_get_uid_gid)
{
	const char * const cg_name = "FuzzerCgroup";
	uid_t tasks_uid, control_uid;
	gid_t tasks_gid, control_gid;

	struct cgroup *cgroup = NULL;
	int ret;
	// case 1
	// cgroup = NULL, tasks_uid = NULL, tasks_gid = NULL, control_uid = NULL,
	// control_uid = NULL
	ret = cgroup_get_uid_gid(cgroup, NULL, NULL, NULL, NULL);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgroup = valid, tasks_uid = NULL, tasks_gid = NULL, control_uid = NULL,
	// control_uid = NULL
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	ret = cgroup_get_uid_gid(cgroup, NULL, NULL, NULL, NULL);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgroup = valid, tasks_uid = valid, tasks_gid = NULL, control_uid = NULL,
	// control_uid = NULL
	ret = cgroup_get_uid_gid(cgroup, &tasks_uid, NULL, NULL, NULL);
	ASSERT_EQ(ret, 50011);

	// case 4
	// cgroup = valid, tasks_uid = valid, tasks_gid = valid, control_uid = NULL,
	// control_uid = NULL
	ret = cgroup_get_uid_gid(cgroup, &tasks_uid, &tasks_gid, NULL, NULL);
	ASSERT_EQ(ret, 50011);

	// case 5
	// cgroup = valid, tasks_uid = valid, tasks_gid = valid, control_uid = valid,
	// control_uid = NULL
	ret = cgroup_get_uid_gid(cgroup, &tasks_uid, &tasks_gid, &control_uid, NULL);
	ASSERT_EQ(ret, 50011);

	// case 6
	// cgroup = valid, tasks_uid = valid, tasks_gid = valid, control_uid = valid,
	// control_uid = valid
	ret = cgroup_get_uid_gid(cgroup, &tasks_uid, &tasks_gid, &control_uid, &control_gid);
	ASSERT_EQ(ret, 0);
}

/**
 * Test arguments passed to set a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_int64 test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_set_value_int64() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_set_value_int64)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	struct cgroup *cgroup = NULL;
	int64_t value = 1024;
	char * name = NULL;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = valid
	ret = cgroup_set_value_int64(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = valid
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpu.shares, so that cgc->index > 0
	ret = cgroup_set_value_int64(cgc, "cpu.shares", 1024);
	ASSERT_EQ(ret, 0);

	ret = cgroup_set_value_int64(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = valid
	name = strdup("cpu.shares");
	ASSERT_NE(name, nullptr);

	ret = cgroup_set_value_int64(cgc, name, value);
	ASSERT_EQ(ret, 0);

	// check if the value was set right
	ret = cgroup_get_value_int64(cgc, name, &value);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(value, 1024);

	free(name);
}

/**
 * Test arguments passed to get a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_get_value_int64 test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_get_value_int64() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_get_value_int64)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	struct cgroup *cgroup = NULL;
	char * name = NULL;
	int64_t value;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_get_value_int64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpu.shares, so that cgc->index > 0
	ret = cgroup_set_value_int64(cgc, "cpu.shares", 1024);
	ASSERT_EQ(ret, 0);

	ret = cgroup_get_value_int64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = NULL
	name = strdup("cpu.shares");
	ASSERT_NE(name, nullptr);

	ret = cgroup_get_value_int64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 4
	// cgc = valid, name = valid, value = NULL
	free(name);
	name = NULL;

	ret = cgroup_get_value_int64(cgc, name, &value);
	ASSERT_EQ(ret, 50011);
}

/**
 * Test arguments passed to set a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_uint64 test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_set_value_uint64() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_set_value_uint64)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	struct cgroup *cgroup = NULL;
	u_int64_t value = 1024;
	char * name = NULL;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = valid
	ret = cgroup_set_value_uint64(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = valid
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpu.shares, so that cgc->index > 0
	ret = cgroup_set_value_uint64(cgc, "cpu.shares", 1024);
	ASSERT_EQ(ret, 0);

	ret = cgroup_set_value_uint64(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = valid
	name = strdup("cpu.shares");
	ASSERT_NE(name, nullptr);

	ret = cgroup_set_value_uint64(cgc, name, value);
	ASSERT_EQ(ret, 0);

	// check if the value was set right
	ret = cgroup_get_value_uint64(cgc, name, &value);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(value, 1024);

	free(name);
}

/**
 * Test arguments passed to get a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_get_value_uint64 test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_get_value_uint64() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_get_value_uint64)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpu";
	struct cgroup *cgroup = NULL;
	char * name = NULL;
	u_int64_t value;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_get_value_uint64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpu.shares, so that cgc->index > 0
	ret = cgroup_set_value_uint64(cgc, "cpu.shares", 1024);
	ASSERT_EQ(ret, 0);

	ret = cgroup_get_value_uint64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = NULL
	name = strdup("cpu.shares");
	ASSERT_NE(name, nullptr);

	ret = cgroup_get_value_uint64(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 4
	// cgc = valid, name = NULL, value = valid
	free(name);
	name = NULL;

	ret = cgroup_get_value_uint64(cgc, name, &value);
	ASSERT_EQ(ret, 50011);
}

/**
 * Test arguments passed to set a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_set_value_bool test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_set_value_bool() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_set_value_bool)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpuset";
	struct cgroup *cgroup = NULL;
	char * name = NULL;
	bool value = 1;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = valid
	ret = cgroup_set_value_bool(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = valid
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpuset.cpu_exclusive, so that cgc->index > 0
	ret = cgroup_set_value_bool(cgc, "cpuset.cpu_exclusive", 0);
	ASSERT_EQ(ret, 0);

	ret = cgroup_set_value_bool(cgc, name, value);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = valid
	name = strdup("cpuset.cpu_exclusive");
	ASSERT_NE(name, nullptr);

	ret = cgroup_set_value_bool(cgc, name, value);
	ASSERT_EQ(ret, 0);

	// check if the value was set right
	ret = cgroup_get_value_bool(cgc, name, &value);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(value, 1);

	free(name);
}

/**
 * Test arguments passed to get a controller's setting
 * @param APIArgsTest googletest test case name
 * @param API_cgroup_get_value_bool test name
 *
 * This test will pass a combination of valid and NULL as
 * arguments to cgroup_get_value_bool() and check if it
 * handles it gracefully.
 */
TEST_F(APIArgsTest, API_cgroup_get_value_bool)
{
	const char * const cg_name = "FuzzerCgroup";
	struct cgroup_controller * cgc = NULL;
	const char * const cg_ctrl = "cpuset";
	struct cgroup *cgroup = NULL;
	char * name = NULL;
	bool value;
	int ret;

	// case 1
	// cgc = NULL, name = NULL, value = NULL
	ret = cgroup_get_value_bool(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 2
	// cgc = valid, name = NULL, value = NULL
	cgroup = cgroup_new_cgroup(cg_name);
	ASSERT_NE(cgroup, nullptr);

	cgc = cgroup_add_controller(cgroup, cg_ctrl);
	ASSERT_NE(cgroup, nullptr);

	// set cpuset.cpu_exclusive, so that cgc->index > 0
	ret = cgroup_set_value_bool(cgc, "cpuset.cpu_exclusive", 0);
	ASSERT_EQ(ret, 0);

	ret = cgroup_get_value_bool(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 3
	// cgc = valid, name = valid, value = NULL
	name = strdup("cpuset.cpu_exclusive");
	ASSERT_NE(name, nullptr);

	ret = cgroup_get_value_bool(cgc, name, NULL);
	ASSERT_EQ(ret, 50011);

	// case 4
	// cgc = valid, name = NULL, value = valid
	free(name);
	name = NULL;

	ret = cgroup_get_value_bool(cgc, name, &value);
	ASSERT_EQ(ret, 50011);
}
