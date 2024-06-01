/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroupv2_subtree_control()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <ftw.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

static const char * const PARENT_DIR = "test011cgroup/";
static const char * const SUBTREE_FILE = "cgroup.subtree_control";


class SubtreeControlTest : public ::testing::Test {
	protected:

	void SetUp() override {
		char tmp_path[FILENAME_MAX];
		int ret, i;
		FILE *f;

		ret = mkdir(PARENT_DIR, S_IRWXU | S_IRWXG | S_IRWXO);
		ASSERT_EQ(ret, 0);

		memset(tmp_path, 0, sizeof(tmp_path));
		ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
			       PARENT_DIR, SUBTREE_FILE);
		ASSERT_GT(ret, 0);

		f = fopen(tmp_path, "w");
		fclose(f);
	}

	/*
	 * https://stackoverflow.com/questions/5467725/how-to-delete-a-directory-and-its-contents-in-posix-c
	 */
	static int unlink_cb(const char *fpath, const struct stat *sb, int typeflag,
		      struct FTW *ftwbuf) {
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

TEST_F(SubtreeControlTest, AddController)
{
	char tmp_path[FILENAME_MAX], buf[4092];
	char ctrlr_name[] = "cpu";
	int ret;
	FILE *f;

	memset(tmp_path, 0, sizeof(tmp_path));
	ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
		       PARENT_DIR, SUBTREE_FILE);
	ASSERT_GT(ret, 0);

	/* erase the contents of the file */
	f = fopen(tmp_path, "w");
	fclose(f);

	ret = cgroupv2_subtree_control(PARENT_DIR, ctrlr_name, true);
	ASSERT_EQ(ret, 0);

	f = fopen(tmp_path, "r");
	ASSERT_NE(f, nullptr);

	while (fgets(buf, sizeof(buf), f))
		ASSERT_STREQ(buf, "+cpu");
	fclose(f);
}

TEST_F(SubtreeControlTest, RemoveController)
{
	char tmp_path[FILENAME_MAX], buf[4092];
	char ctrlr_name[] = "memory";
	int ret;
	FILE *f;

	memset(tmp_path, 0, sizeof(tmp_path));
	ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
		       PARENT_DIR, SUBTREE_FILE);
	ASSERT_GT(ret, 0);

	/* erase the contents of the file */
	f = fopen(tmp_path, "w");
	fclose(f);

	ret = cgroupv2_subtree_control(PARENT_DIR, ctrlr_name, false);
	ASSERT_EQ(ret, 0);

	f = fopen(tmp_path, "r");
	ASSERT_NE(f, nullptr);

	while (fgets(buf, sizeof(buf), f))
		ASSERT_STREQ(buf, "-memory");
	fclose(f);
}
