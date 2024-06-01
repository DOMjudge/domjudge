// SPDX-License-Identifier: LGPL-2.1-only
/**
 * Copyright (C) 2009 Red Hat, Inc. All Rights Reserved.
 * Written by Ivana Hutarova Varekova <varekova@redhat.com>
 */

#include "tools-common.h"

#include <libcgroup.h>
#include <libcgroup-internal.h>

#include <unistd.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>
#include <stdio.h>

enum flag {
	/*
	 * the flag set if there is a cgroup on
	 * output if there is no one we want to
	 * display all cgroups
	 */
	FL_LIST = 1
};

static inline void trim_filepath(char *path)
{
	int len;

	len = strlen(path) - 1;
	while (path[len] == '/')
		len--;

	path[len + 1] = '\0';
}

static void usage(int status, const char *program_name)
{
	if (status != 0) {
		err("Wrong input parameters, try %s -h' for more information.\n", program_name);
		return;
	}

	info("Usage: %s [-h] [[-g] <controllers>:<path>] [...]\n", program_name);
	info("List all cgroups\n");
	info("  -g <controllers>:<path>	Control group to be ");
	info("displayed (-g is optional)\n");
	info("  -h, --help			Display this help\n");
#ifdef WITH_SYSTEMD
	info("  -b				Ignore default systemd delegate hierarchy\n");
#endif
	info("(Note: currently supported on cgroups v1 only)\n");
}

/*
 * if the info about controller "name" should be printed, then the function
 * returns nonzero value
 */
static int is_ctlr_on_list(struct cgroup_group_spec *cgroup_list, const char *name)
{
	int j;

	for (j = 0; cgroup_list->controllers[j] != NULL; j++)
		if (strcmp(cgroup_list->controllers[j], name) == 0)
			return 1;

	return 0;
}

static void print_info(struct cgroup_file_info *info, char *name, int pref)
{
	if (info->type == CGROUP_FILE_TYPE_DIR) {
		if (info->full_path[pref] ==  '/')
			info("%s:%s\n", name, &info->full_path[pref]);
		else
			info("%s:/%s\n", name, &info->full_path[pref]);
	}
}

/* display controller:/input_path cgroups */
static int display_controller_data(char *input_path, char *controller, char *name)
{
	char cgroup_dir_path[FILENAME_MAX];
	char input_dir_path[FILENAME_MAX];
	struct cgroup_file_info info;
	int lvl, len, ret;
	void *handle;

	ret = cgroup_walk_tree_begin(controller, input_path, 0, &handle, &info, &lvl);
	if (ret != 0)
		return ret;

	strncpy(cgroup_dir_path, info.full_path, FILENAME_MAX);
	cgroup_dir_path[sizeof(cgroup_dir_path) - 1] = '\0';
	/* remove problematic  '/' characters from cgroup directory path */
	trim_filepath(cgroup_dir_path);

	strncpy(input_dir_path, input_path, FILENAME_MAX);
	input_dir_path[sizeof(input_dir_path) - 1] = '\0';

	/* remove problematic  '/' characters from input directory path */
	trim_filepath(input_dir_path);
	len  = strlen(cgroup_dir_path) - strlen(input_dir_path);
	print_info(&info, name, len);

	while ((ret = cgroup_walk_tree_next(0, &handle, &info, lvl)) == 0)
		print_info(&info, name, len);

	cgroup_walk_tree_end(&handle);
	if (ret == ECGEOF)
		ret = 0;

	return ret;
}

/*
 * print data about input cgroup_list cgroups if FL_LIST flag is set then
 * if the function does not find the cgroup it returns ECGEOF
 */
static int print_cgroup(struct cgroup_group_spec *cgroup_spec, int flags)
{
	struct cgroup_mount_point controller;
	char all_conts[FILENAME_MAX];
	char con_name[FILENAME_MAX];
	char path[FILENAME_MAX];
	int output = 0;
	void *handle;
	int ret = 0;

	path[0] = '\0';
	con_name[0] = '\0';
	all_conts[0] = '\0';

	ret = cgroup_get_controller_begin(&handle, &controller);

	/* go through the list of controllers/mount point pairs */
	while (ret == 0) {
		if (strcmp(path, controller.path) == 0) {
			/* if it is still the same mount point */
			strncat(all_conts, ",",	FILENAME_MAX-strlen(all_conts)-1);
			strncat(all_conts, controller.name, FILENAME_MAX-strlen(all_conts)-1);
			all_conts[sizeof(all_conts) - 1] = '\0';
		} else {
			/* we got new mount point, print it if needed */
			if (output) {
				ret = display_controller_data(cgroup_spec->path, con_name,
							      all_conts);
				if (ret)
					return ret;
				if ((flags & FL_LIST) != 0) {
					/* we succesfully finish printing */
					output = 0;
					break;
				}
			}

			output = 0;
			strncpy(all_conts, controller.name, FILENAME_MAX);
			all_conts[FILENAME_MAX-1] = '\0';

			strncpy(con_name, controller.name, FILENAME_MAX);
			con_name[FILENAME_MAX-1] = '\0';

			strncpy(path, controller.path, FILENAME_MAX);
			path[FILENAME_MAX-1] = '\0';
		}

		/* set output flag */
		if ((output == 0) && (!(flags & FL_LIST) ||
		    (is_ctlr_on_list(cgroup_spec, controller.name))))
			output = 1;

		/* the actual controller should not be printed */
		ret = cgroup_get_controller_next(&handle, &controller);
	}

	cgroup_get_controller_end(&handle);
	if (ret != ECGEOF)
		return ret;

	if (output)
		ret = display_controller_data(cgroup_spec->path, con_name, all_conts);

	return ret;
}


static int cgroup_list_cgroups(char *tname, struct cgroup_group_spec *cgroup_list[], int flags)
{
	int final_ret = 0;
	int ret = 0;
	int i = 0;

	/* initialize libcgroup */
	ret = cgroup_init();
	if (ret) {
		err("cgroups can't be listed: %s\n", cgroup_strerror(ret));
		return ret;
	}

	if ((flags & FL_LIST) == 0) {
		struct cgroup_group_spec *cgroup_spec;

		cgroup_spec = calloc(1, sizeof(struct cgroup_group_spec));
		/* we have to print all cgroups */
		ret = print_cgroup(cgroup_spec,  flags);
		free(cgroup_spec);
		if (ret == 0) {
			final_ret = 0;
		} else {
			final_ret = ret;
			err("cgroups can't be listed: %s\n", cgroup_strerror(ret));
		}
	} else {
		/* we have he list of controllers which should be print */
		while ((cgroup_list[i] != NULL)	&& ((ret == ECGEOF) || (ret == 0))) {

			ret = print_cgroup(cgroup_list[i], flags);
			if (ret != 0) {
				if (ret == ECGEOF) {
					/* controller was not found */
					final_ret = ECGFAIL;
				} else {
					/* other problem */
					final_ret = ret;
				}

				err("%s: cannot find group %s..:%s: %s\n", tname,
				    cgroup_list[i]->controllers[0], cgroup_list[i]->path,
				    cgroup_strerror(final_ret));
			}
			i++;
		}
	}

	return final_ret;
}

int main(int argc, char *argv[])
{
	static struct option options[] = {
		{"help", 0, 0, 'h'},
		{"group", required_argument, NULL, 'g'},
		{0, 0, 0, 0}
	};

	struct cgroup_group_spec *cgroup_list[CG_HIER_MAX];
	int ignore_default_systemd_delegate_slice = 0;
	int flags = 0;
	int ret = 0;
	int c;
	int i;

	memset(cgroup_list, 0, sizeof(cgroup_list));

	/* parse arguments */
#ifdef WITH_SYSTEMD
	while ((c = getopt_long(argc, argv, "hg:b", options, NULL)) > 0) {
		switch (c) {
		case 'b':
			ignore_default_systemd_delegate_slice = 1;
			break;
#else
	while ((c = getopt_long(argc, argv, "hg:", options, NULL)) > 0) {
		switch (c) {
#endif
		case 'h':
			usage(0, argv[0]);
			ret = 0;
			goto err;
		case 'g':
			ret = parse_cgroup_spec(cgroup_list, optarg, CG_HIER_MAX);
			if (ret) {
				err("%s: cgroup controller and path parsing failed (%s)\n",
				    argv[0], optarg);
				return ret;
			}
			break;
		default:
			usage(1, argv[0]);
			ret = EXIT_BADARGS;
			goto err;
		}
	}

	/* initialize libcg */
	ret = cgroup_init();
	if (ret) {
		err("%s: libcgroup initialization failed: %s\n", argv[0], cgroup_strerror(ret));
		goto err;
	}

	/* this is false always for disable-systemd */
	if (!ignore_default_systemd_delegate_slice)
		cgroup_set_default_systemd_cgroup();

	/* read the list of controllers */
	while (optind < argc) {
		ret = parse_cgroup_spec(cgroup_list, argv[optind], CG_HIER_MAX);
		if (ret) {
			err("%s: cgroup controller an path parsing failed (%s)\n", argv[0],
			    argv[optind]);
			return -1;
		}
		optind++;
	}

	if (cgroup_list[0] != NULL) {
		/* cgroups on input */
		flags |= FL_LIST;
	}

	/* print the information based on list of input cgroups and flags */
	ret = cgroup_list_cgroups(argv[0], cgroup_list, flags);

err:
	if (cgroup_list[0]) {
		for (i = 0; i < CG_HIER_MAX; i++) {
			if (cgroup_list[i])
				cgroup_free_group_spec(cgroup_list[i]);
		}
	}

	return ret;
}
