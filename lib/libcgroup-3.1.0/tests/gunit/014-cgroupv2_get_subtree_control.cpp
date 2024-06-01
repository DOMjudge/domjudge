/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroupv2_get_subtree_control()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <fcntl.h>
#include <ftw.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

static const char * const PARENT_DIR = "test013cgroup/";
static const char * const SUBTREE_FILE = "cgroup.subtree_control";


class GetSubtreeControlTest : public ::testing::Test {
	protected:

	void SetUp() override {
		char tmp_path[FILENAME_MAX];
		int ret, i;
		FILE *f;

		ret = mkdir(PARENT_DIR, S_IRWXU | S_IRWXG | S_IRWXO);
		ASSERT_EQ(ret, 0);
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

static void write_subtree_file(const char * const contents, ssize_t len)
{
	char tmp_path[FILENAME_MAX];
	ssize_t bytes_written;
	int ret, fd;

	memset(tmp_path, 0, sizeof(tmp_path));
	ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
		       PARENT_DIR, SUBTREE_FILE);
	ASSERT_GT(ret, 0);

	fd = open(tmp_path, O_WRONLY | O_TRUNC | O_CREAT, S_IRWXU | S_IRWXG);
	ASSERT_GT(fd, 0);

	bytes_written = write(fd, contents, len);
	ASSERT_EQ(bytes_written, len);
	close(fd);
}

TEST_F(GetSubtreeControlTest, SingleControllerEnabled)
{
	char ctrlr_name[] = "cpu";
	char subtree_contents[] = "cpu\n";
	bool enabled = false;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(enabled, true);
}

TEST_F(GetSubtreeControlTest, SingleControllerNoMatch)
{
	char ctrlr_name[] = "cpu";
	char subtree_contents[] = "cpuset\n";
	bool enabled = true;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, ECGROUPNOTMOUNTED);
	ASSERT_EQ(enabled, false);
}

TEST_F(GetSubtreeControlTest, SingleControllerNoMatch2)
{
	char ctrlr_name[] = "cpuset";
	char subtree_contents[] = "cpu\n";
	bool enabled = true;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, ECGROUPNOTMOUNTED);
	ASSERT_EQ(enabled, false);
}

TEST_F(GetSubtreeControlTest, MultipleControllersEnabled)
{
	char ctrlr_name[] = "cpu";
	char subtree_contents[] = "cpu cpuset io memory pids\n";
	bool enabled = false;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(enabled, true);
}

TEST_F(GetSubtreeControlTest, MultipleControllersEnabled2)
{
	char ctrlr_name[] = "pids";
	char subtree_contents[] = "cpu cpuset io memory pids\n";
	bool enabled = false;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, 0);
	ASSERT_EQ(enabled, true);
}

TEST_F(GetSubtreeControlTest, MultipleControllersNoMatch)
{
	char ctrlr_name[] = "network";
	char subtree_contents[] = "cpu cpuset io memory pids\n";
	bool enabled = true;
	int ret;

	write_subtree_file(subtree_contents, strlen(subtree_contents));

	ret = cgroupv2_get_subtree_control(PARENT_DIR, ctrlr_name, &enabled);
	ASSERT_EQ(ret, ECGROUPNOTMOUNTED);
	ASSERT_EQ(enabled, false);
}
