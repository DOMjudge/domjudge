/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for cgroup_get_cgroup()
 *
 * Copyright (c) 2020 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <bits/stdc++.h>
#include <string>
#include <vector>
using namespace std;

#include <ftw.h>

#include "gtest/gtest.h"
#include "libcgroup-internal.h"

#define MAX_NAMES 5

enum ctrl_enum {
	CTRL_CPU,
	CTRL_FREEZER,
	CTRL_MEMORY,
	CTRL_CPUSET,
	CTRL_NAMESPACES,
	CTRL_NETNS,

	CTRL_CNT
};

static const char * const PARENT_DIR = "test006cgroup";

static const char * const CONTROLLERS[] = {
	"cpu",
	"freezer",
	"memory",
	"cpuset",
	"namespaces",
	"netns",
};
static const int CONTROLLERS_CNT =
	sizeof(CONTROLLERS) / sizeof(CONTROLLERS[0]);

static const char * const NAMES[][MAX_NAMES] = {
	{"tasks", "cpu.shares", "cpu.weight", "cpu.foo", NULL},
	{"tasks", NULL, NULL, NULL, NULL},
	{"tasks", "memory.limit_in_bytes", "memory.memsw.limit_in_bytes", NULL, NULL},
	{"tasks", "cpuset.exclusive", "cpuset.foo", "cpuset.bar", "cpuset.x"},
	{"tasks", "namespaces.blah", NULL, NULL, NULL},
	{"tasks", "netns.foo", "netns.bar", "netns.baz", NULL},
};
static const int NAMES_CNT = sizeof(NAMES) / sizeof(NAMES[0]);

static const char * const VALUES[][MAX_NAMES] = {
	{"1234", "512", "100", "abc123", NULL},
	{"2345\n3456", NULL, NULL, NULL, NULL},
	{"456\n678\n890", "8675309", "1024000", NULL, NULL},
	{"\0", "1", "limit=32412039", "9223372036854771712", "partition"},
	{"59832", "The Quick Brown Fox", NULL, NULL, NULL},
	{"987\n654", "root", "/sys/fs", "0xdeadbeef", NULL},
};
static const int VALUES_CNT = sizeof(VALUES) / sizeof(VALUES[0]);

static const char * const CG_NAME = "tomcatcg";
static const mode_t MODE = S_IRWXU | S_IRWXG | S_IRWXO;

class CgroupGetCgroupTest : public ::testing::Test {
	protected:

	void CreateNames(const char * const names[],
			 const char * const values[],
			 const char * const ctrl_name)
	{
		char tmp_path[FILENAME_MAX];
		FILE *f;
		int i;

		for (i = 0; i < NAMES_CNT; i++) {
			if (names[i] == NULL)
				break;

			memset(tmp_path, 0, sizeof(tmp_path));
			snprintf(tmp_path, FILENAME_MAX - 1, "%s/%s/%s/%s",
				 PARENT_DIR, ctrl_name, CG_NAME, names[i]);

			f = fopen(tmp_path, "w");
			ASSERT_NE(f, nullptr);

			fprintf(f, "%s", values[i]);
			fclose(f);
		}
	}

	void SetUp() override
	{
		char tmp_path[FILENAME_MAX];
		int i, j, names_len, ret;

		ASSERT_EQ(NAMES_CNT, CONTROLLERS_CNT);
		ASSERT_EQ(NAMES_CNT, VALUES_CNT);

		ret = cgroup_init();
		ASSERT_EQ(ret, 0);

		ret = mkdir(PARENT_DIR, MODE);
		ASSERT_EQ(ret, 0);

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
				 "%s/%s", PARENT_DIR, CONTROLLERS[i]);
			cg_mount_table[i].version = CGROUP_V1;

			ret = mkdir(cg_mount_table[i].mount.path, MODE);
			ASSERT_EQ(ret, 0);

			/*
			 * arbitrarily don't make the cgroup directory in
			 * the freezer controller
			 */
			if (i == CTRL_FREEZER)
				continue;

			memset(tmp_path, 0, sizeof(tmp_path));
			snprintf(tmp_path, FILENAME_MAX - 1, "%s/%s/%s",
				 PARENT_DIR, CONTROLLERS[i], CG_NAME);
			ret = mkdir(tmp_path, MODE);
			ASSERT_EQ(ret, 0);

			CreateNames(NAMES[i], VALUES[i], CONTROLLERS[i]);
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

	void TearDown() override
	{
		int ret = 0;

		ret = rmrf(PARENT_DIR);
		ASSERT_EQ(ret, 0);
	}
};

static void vectorize_cg(const struct cgroup * const cg,
			 vector<string>& cg_vec)
{
	const char *cgname, *cgcname, *value;
	int i, j;

	for (i = 0; i < cg->index; i++) {
		for (j = 0; j < cg->controller[i]->index; j++) {
			string cgname(cg->name);
			string cgcname(cg->controller[i]->name);
			string name(cg->controller[i]->values[j]->name);
			string value(cg->controller[i]->values[j]->value);

			cg_vec.push_back(cgcname + "+" + cgname + "+" +
					 name + "+" + value);
		}
	}

	sort(cg_vec.begin(), cg_vec.end());
}

static void vectorize_testdata(vector<string>& test_vec)
{
	string cgname(CG_NAME);
	int i, j;

	for (i = 0; i < CTRL_CNT; i++) {
		for (j = 0; j < MAX_NAMES; j++) {
			if (NAMES[i][j] == NULL)
				continue;

			if (strcmp(NAMES[i][j], "tasks") == 0)
				/*
				 * The tasks files isn't listed by
				 * cgroup_get_cgroup()
				 */
				continue;

			string cgcname(CONTROLLERS[i]);
			string name(NAMES[i][j]);
			string value(VALUES[i][j]);

			test_vec.push_back(cgcname + "+" + cgname + "+" +
					   name + "+" + value);
		}
	}

	sort(test_vec.begin(), test_vec.end());
}

TEST_F(CgroupGetCgroupTest, CgroupGetCgroup1)
{
	vector<string> cg_vec, test_vec;
	struct cgroup *cg = NULL;
	int ret;

	cg = cgroup_new_cgroup(CG_NAME);
	ASSERT_NE(cg, nullptr);

	ret = cgroup_get_cgroup(cg);
	ASSERT_EQ(ret, 0);

	vectorize_cg(cg, cg_vec);
	vectorize_testdata(test_vec);

	ASSERT_EQ(cg_vec, test_vec);

	if (cg)
		free(cg);
}

/*
 * This test must be last because it makes destructive changes to the cgroup hierarchy
 */
TEST_F(CgroupGetCgroupTest, CgroupGetCgroup_NoTasksFile)
{
	char tmp_path[FILENAME_MAX];
	struct cgroup *cg = NULL;
	int ret;

	snprintf(tmp_path, FILENAME_MAX - 1, "%s/%s/%s/tasks",
		 PARENT_DIR, CONTROLLERS[CONTROLLERS_CNT - 1], CG_NAME);
	ret = rmrf(tmp_path);
	ASSERT_EQ(ret, 0);

	cg = cgroup_new_cgroup(CG_NAME);
	ASSERT_NE(cg, nullptr);

	ret = cgroup_get_cgroup(cg);
	ASSERT_EQ(ret, ECGOTHER);

	if (cg)
		free(cg);
}
