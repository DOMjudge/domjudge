/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroup_set_values_recursive()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <ftw.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"

static const char * const PARENT_DIR = "test009cgroup/";

static const char * const NAMES[] = {
	"cpu.weight",
	"cpu.weight.nice",
	"cpu.foo",
	"cpu.bar"
};
static const int NAMES_CNT = sizeof(NAMES) / sizeof(NAMES[0]);

static const char * const VALUES[] = {
	"999",
	"15",
	"random",
	"data"
};
static const int VALUES_CNT = sizeof(VALUES) / sizeof(VALUES[0]);


class SetValuesRecursiveTest : public ::testing::Test {
	protected:

	void SetUp() override {
		char tmp_path[FILENAME_MAX];
		int ret, i;
		FILE *f;

		ASSERT_EQ(NAMES_CNT, VALUES_CNT);

		ret = mkdir(PARENT_DIR, S_IRWXU | S_IRWXG | S_IRWXO);
		ASSERT_EQ(ret, 0);

		for (i = 0; i < NAMES_CNT; i++) {
			memset(tmp_path, 0, sizeof(tmp_path));
			ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
				       PARENT_DIR, NAMES[i]);
			ASSERT_GT(ret, 0);

			f = fopen(tmp_path, "w");
			fclose(f);
		}
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

TEST_F(SetValuesRecursiveTest, SuccessfulSetValues)
{
	char tmp_path[FILENAME_MAX], buf[4092];
	struct cgroup_controller ctrlr = {0};
	int ret, i;
	char *val;
	FILE *f;

	ret = snprintf(ctrlr.name, CONTROL_NAMELEN_MAX, "cpu");
	ASSERT_GT(ret, 0);

	for (i = 0; i < NAMES_CNT; i++) {
		ctrlr.values[i] = (struct control_value *)calloc(1,
					sizeof(struct control_value));
		ASSERT_NE(ctrlr.values[i], nullptr);

		strncpy(ctrlr.values[i]->name, NAMES[i], FILENAME_MAX);
		strncpy(ctrlr.values[i]->value, VALUES[i],
			CG_CONTROL_VALUE_MAX);
		if (i == 0)
			ctrlr.values[i]->dirty = true;
		else
			ctrlr.values[i]->dirty = false;
		ctrlr.index++;
	}

	ret = cgroup_set_values_recursive(PARENT_DIR, &ctrlr, false);
	ASSERT_EQ(ret, 0);

	for (i = 0; i < NAMES_CNT; i++) {
		memset(tmp_path, 0, sizeof(tmp_path));
		ret = snprintf(tmp_path, FILENAME_MAX - 1, "%s%s",
			       PARENT_DIR, NAMES[i]);
		ASSERT_GT(ret, 0);

		f = fopen(tmp_path, "r");
		ASSERT_NE(f, nullptr);

		val = fgets(buf, sizeof(buf), f);
		ASSERT_NE(val, nullptr);
		ASSERT_STREQ(buf, VALUES[i]);
		fclose(f);
	}
}
