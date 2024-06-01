/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroupv2_controller_enabled()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <ftw.h>
#include <sys/stat.h>
#include <sys/types.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

static const char * const PARENT_DIR = "test015cgroup";
static const mode_t MODE = S_IRWXU | S_IRWXG | S_IRWXO;

static const char * const CHILD_DIRS[] = {
	"test1-v1cgroup",
	"test2-rootcgroup",
	"test3-ctrlrenabled",
	"test4-ctrlrdisabled",
};
static const int CHILD_DIRS_CNT =
	sizeof(CHILD_DIRS) / sizeof(CHILD_DIRS[0]);

static const char * const CONTROLLERS[] = {
	"cpu",
	"cpuset",
	"io",
	"memory",
	"net_cls",
	"pids",
};
static const int CONTROLLERS_CNT =
	sizeof(CONTROLLERS) / sizeof(CONTROLLERS[0]);

static const enum cg_version_t VERSIONS[] = {
	CGROUP_V2,
	CGROUP_V1,
	CGROUP_V2,
	CGROUP_V2,
	CGROUP_V2,
	CGROUP_V2,
};
static const int VERSIONS_CNT =
	sizeof(VERSIONS) / sizeof(VERSIONS[0]);

class CgroupV2ControllerEnabled : public ::testing::Test {
	protected:

	void InitChildDir(const char dirname[])
	{
		char tmp_path[FILENAME_MAX] = {0};
		int ret;

		/* create the directory */
		snprintf(tmp_path, FILENAME_MAX - 1, "%s/%s",
			 PARENT_DIR, dirname);
		ret = mkdir(tmp_path, MODE);
		ASSERT_EQ(ret, 0);
	}

	void InitMountTable(void)
	{
		char tmp_path[FILENAME_MAX] = {0};
		int ret, i;
		FILE *f;

		ASSERT_EQ(VERSIONS_CNT, CONTROLLERS_CNT);

		snprintf(tmp_path, FILENAME_MAX - 1,
			 "%s/cgroup.subtree_control", PARENT_DIR);

		f = fopen(tmp_path, "w");
		ASSERT_NE(f, nullptr);
		fprintf(f, "cpu io memory pids\n");
		fclose(f);

		/*
		 * Artificially populate the mount table with local
		 * directories
		 */
		memset(&cg_mount_table, 0, sizeof(cg_mount_table));
		memset(&cg_namespace_table, 0, sizeof(cg_namespace_table));

		for (i = 0; i < CONTROLLERS_CNT; i++) {
			snprintf(cg_mount_table[i].name, CONTROL_NAMELEN_MAX,
				 "%s", CONTROLLERS[i]);
			snprintf(cg_mount_table[i].mount.path, FILENAME_MAX,
				 "%s", PARENT_DIR);
			cg_mount_table[i].version = VERSIONS[i];
		}
	}

	void SetUp() override
	{
		int ret, i;

		ret = mkdir(PARENT_DIR, MODE);
		ASSERT_EQ(ret, 0);

		InitMountTable();

		for (i = 0; i < CHILD_DIRS_CNT; i++)
			InitChildDir(CHILD_DIRS[i]);
	}

	/*
	 * https://stackoverflow.com/questions/5467725/how-to-delete-a-directory-and-its-contents-in-posix-c
	 */
	static int unlink_cb(const char *fpath, const struct stat *sb, int typeflag,
		      struct FTW *ftwbuf)
	{
		return remove(fpath);
	}

	int rmrf(const char * const path)
	{
		return nftw(path, unlink_cb, 64, FTW_DEPTH | FTW_PHYS);
	}

	void TearDown() override
	{
		int ret = 0;

		ret = rmrf(PARENT_DIR);
		ASSERT_EQ(ret, 0);
	}
};

TEST_F(CgroupV2ControllerEnabled, CgroupV1Controller)
{
	char ctrlr_name[] = "cpuset";
	char cg_name[] = "foo";
	int ret;

	ret = cgroupv2_controller_enabled(cg_name, ctrlr_name);
	ASSERT_EQ(ret, 0);
}

TEST_F(CgroupV2ControllerEnabled, RootCgroup)
{
	char ctrlr_name[] = "cpu";
	char cg_name[] = "/";
	int ret;

	ret = cgroupv2_controller_enabled(cg_name, ctrlr_name);
	ASSERT_EQ(ret, 0);
}

TEST_F(CgroupV2ControllerEnabled, ControllerEnabled)
{
	char ctrlr_name[] = "pids";
	char cg_name[] = "test3-ctrlrenabled";
	int ret;

	ret = cgroupv2_controller_enabled(cg_name, ctrlr_name);
	ASSERT_EQ(ret, 0);
}

TEST_F(CgroupV2ControllerEnabled, ControllerDisabled)
{
	char ctrlr_name[] = "net_cls";
	char cg_name[] = "test4-ctrlrdisabled";
	int ret;

	ret = cgroupv2_controller_enabled(cg_name, ctrlr_name);
	ASSERT_EQ(ret, ECGROUPNOTMOUNTED);
}
