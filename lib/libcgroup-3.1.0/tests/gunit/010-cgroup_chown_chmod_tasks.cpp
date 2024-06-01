/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroup_chown_chmod_tasks()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <ftw.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

static const char * const PARENT_DIR = "test010cgroup";
static const mode_t MODE = S_IRWXU | S_IRWXG | S_IRWXO;

class ChownChmodTasksTest : public ::testing::Test {
	protected:

	void SetUp() override {
		char tasks_path[FILENAME_MAX];
		int ret;
		FILE *f;

		ret = mkdir(PARENT_DIR, MODE);
		ASSERT_EQ(ret, 0);

		memset(tasks_path, 0, sizeof(tasks_path));
		ret = snprintf(tasks_path, FILENAME_MAX - 1, "%s/tasks",
			       PARENT_DIR);
		ASSERT_GT(ret, 0);

		f = fopen(tasks_path, "w");
		fclose(f);
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

	void TearDown() override {
		int ret;

		ret = rmrf(PARENT_DIR);
		ASSERT_EQ(ret, 0);
	}
};

TEST_F(ChownChmodTasksTest, SuccessfulChownChmod)
{
	mode_t mode = S_IRUSR | S_IWUSR | S_IWGRP | S_IROTH;
	char tasks_path[FILENAME_MAX];
	uid_t uid = getuid();
	gid_t gid = getgid();
	struct stat statbuf;
	int ret;

	ret = cgroup_chown_chmod_tasks(PARENT_DIR, uid, gid, mode);
	ASSERT_EQ(ret, 0);

	memset(tasks_path, 0, sizeof(tasks_path));
	ret = snprintf(tasks_path, FILENAME_MAX - 1, "%s/tasks",
		       PARENT_DIR);
	ASSERT_GT(ret, 0);

	ret = stat(tasks_path, &statbuf);
	ASSERT_EQ(ret, 0);

	ASSERT_EQ(statbuf.st_uid, uid);
	ASSERT_EQ(statbuf.st_gid, gid);
	ASSERT_EQ(statbuf.st_mode & 0777, mode);
}
